#!/usr/bin/env python3
"""Restore base-recipe recipe_components from a legacy ingredients CSV snapshot."""

from __future__ import annotations

import csv
import itertools
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LEGACY_CSV = Path("/tmp/ingredients_may19.csv")
CURRENT_CSV = ROOT / "database/data/menu/ingredients.csv"
ING_POOL_JSON = Path("/tmp/ing_pool.json")
OUTPUT_CSV = CURRENT_CSV


def fnum(value: str | None) -> float:
    if value is None:
        return 0.0
    text = str(value).strip()
    if text == "":
        return 0.0
    try:
        return float(text)
    except ValueError:
        return 0.0


def parse_components(cell: str) -> list[tuple[int, float]]:
    segments: list[tuple[int, float]] = []
    for segment in re.split(r"[,|]", cell):
        segment = segment.strip()
        if not segment or ":" not in segment:
            continue
        id_part, amount_part = segment.split(":", 1)
        id_part = id_part.strip()
        amount_part = amount_part.strip().replace(",", ".")
        if not id_part.isdigit():
            continue
        amount_match = re.match(r"^(\d+(?:\.\d+)?)", amount_part)
        if not amount_match:
            continue
        segments.append((int(id_part), float(amount_match.group(1))))
    return segments


def normalize(text: str) -> str:
    text = text.lower()
    text = re.sub(r"[^a-z0-9]+", " ", text)
    return re.sub(r"\s+", " ", text).strip()


def tokenize(text: str) -> set[str]:
    return {t for t in normalize(text).split() if len(t) > 2}


def batch_nutrition(ingredients_by_name: dict[str, dict], rows: list[tuple[str, float]]) -> dict[str, float]:
    totals = {"calories": 0.0, "protein": 0.0, "carbs": 0.0, "fat": 0.0}
    for name, grams in rows:
        ing = ingredients_by_name.get(name)
        if ing is None:
            continue
        factor = grams / 100.0
        totals["calories"] += fnum(ing["calories"]) * factor
        totals["protein"] += fnum(ing["protein"]) * factor
        totals["carbs"] += fnum(ing["carbs"]) * factor
        totals["fat"] += fnum(ing["fat"]) * factor
    return totals


def nutrition_error(target: dict[str, float], actual: dict[str, float]) -> float:
    weights = {"calories": 1.0, "protein": 8.0, "carbs": 2.0, "fat": 4.0}
    error = 0.0
    for key, weight in weights.items():
        t = target[key]
        a = actual[key]
        if t <= 0 and a <= 0:
            continue
        denom = max(t, 1.0)
        error += weight * ((a - t) / denom) ** 2
    return error


def instruction_candidates(context: str, ingredient_names: list[str], recipe_name: str) -> list[str]:
    context_norm = normalize(context)
    recipe_norm = normalize(recipe_name)
    candidates: list[str] = []

    for name in ingredient_names:
        if name == recipe_name:
            continue
        if name.endswith("(Base)") and name != recipe_name:
            # Allow nested base recipes when explicitly named in context.
            if normalize(name) not in context_norm:
                continue
        name_norm = normalize(name)
        if name_norm and name_norm in context_norm:
            candidates.append(name)
            continue
        overlap = tokenize(name) & tokenize(context)
        if len(overlap) >= 2 or (len(overlap) == 1 and len(name_norm.split()) <= 2):
            candidates.append(name)

    if len(candidates) < len(context_norm.split()) // 8:
        # Fallback: include common pantry items referenced loosely.
        for name in ingredient_names:
            if name in candidates or name == recipe_name:
                continue
            if any(word in context_norm for word in tokenize(name)):
                candidates.append(name)

    return sorted(set(candidates))


def solve_recipe_assignment(
    segments: list[tuple[int, float]],
    legacy_row: list[str],
    idx: dict[str, int],
    ingredients_by_name: dict[str, dict],
    ingredient_names: list[str],
    recipe_name: str,
    current_id_to_name: dict[int, str],
) -> list[tuple[str, float]]:
    if all(legacy_id in current_id_to_name for legacy_id, _ in segments):
        return [(current_id_to_name[legacy_id], grams) for legacy_id, grams in segments]

    context = " ".join(
        [
            legacy_row[idx["description"]] if idx["description"] < len(legacy_row) else "",
            legacy_row[idx["instructions"]] if idx["instructions"] < len(legacy_row) else "",
            recipe_name,
        ]
    )

    grams_slots = [grams for _, grams in segments]
    total_grams = fnum(legacy_row[idx["finished_weight_grams"]])
    if total_grams <= 0:
        total_grams = sum(grams_slots) or 1.0

    target_per_100 = {
        "calories": fnum(legacy_row[idx["calories"]]),
        "protein": fnum(legacy_row[idx["protein"]]),
        "carbs": fnum(legacy_row[idx["carbs"]]),
        "fat": fnum(legacy_row[idx["fat"]]),
    }
    target_batch = {k: target_per_100[k] * total_grams / 100.0 for k in target_per_100}

    candidates = instruction_candidates(context, ingredient_names, recipe_name)
    if len(candidates) < len(segments):
        candidates = [
            name
            for name in ingredient_names
            if name != recipe_name and not (name.endswith("(Base)") and normalize(name) not in normalize(context))
        ]

    best_rows: list[tuple[str, float]] = []
    best_error = float("inf")

    for combo in itertools.permutations(candidates, len(segments)):
        rows = list(zip(combo, grams_slots))
        actual = batch_nutrition(ingredients_by_name, rows)
        error = nutrition_error(target_batch, actual)
        if error < best_error:
            best_error = error
            best_rows = rows

    return best_rows


def to_name_cell(rows: list[tuple[str, float]]) -> str:
    parts = []
    for name, grams in rows:
        amount = ("%g" % grams).rstrip("0").rstrip(".") if grams % 1 else str(int(grams))
        parts.append(f"{name} ({amount}g)")
    return " | ".join(parts)


def main() -> int:
    if not LEGACY_CSV.is_file():
        print(f"Missing legacy CSV: {LEGACY_CSV}", file=sys.stderr)
        return 1
    if not ING_POOL_JSON.is_file():
        print(f"Missing ingredient pool JSON: {ING_POOL_JSON}", file=sys.stderr)
        return 1

    ingredients = json.loads(ING_POOL_JSON.read_text())
    ingredients_by_name = {ing["name"]: ing for ing in ingredients}
    ingredient_names = [ing["name"] for ing in ingredients]
    current_id_to_name = {ing["id"]: ing["name"] for ing in ingredients}

    legacy_rows = list(csv.reader(LEGACY_CSV.open(newline="")))
    legacy_idx = {h: i for i, h in enumerate(legacy_rows[0])}

    legacy_bases: dict[str, list[str]] = {}
    resolved_cells: dict[str, str] = {}

    for row in legacy_rows[1:]:
        if len(row) <= legacy_idx["is_base_recipe"]:
            continue
        if row[legacy_idx["is_base_recipe"]] != "1":
            continue
        name = row[legacy_idx["name"]]
        components = row[legacy_idx["recipe_components"]].strip()
        if not components:
            continue
        legacy_bases[name] = row

        if not re.search(r"(?:^|[|,])\s*\d+\s*:\s*\d", components):
            resolved_cells[name] = components
            continue

        segments = parse_components(components)
        rows = solve_recipe_assignment(
            segments,
            row,
            legacy_idx,
            ingredients_by_name,
            ingredient_names,
            name,
            current_id_to_name,
        )
        if len(rows) != len(segments):
            print(f"Warning: partial assignment for {name}", file=sys.stderr)
        resolved_cells[name] = to_name_cell(rows)

    current_rows = list(csv.reader(CURRENT_CSV.open(newline="")))
    current_idx = {h: i for i, h in enumerate(current_rows[0])}

    restored = 0
    skipped = 0
    for row in current_rows[1:]:
        if len(row) <= current_idx["is_base_recipe"]:
            continue
        if row[current_idx["is_base_recipe"]] not in {"1", "true", "TRUE"}:
            continue
        name = row[current_idx["name"]]
        if row[current_idx["recipe_components"]].strip():
            skipped += 1
            continue
        if name not in resolved_cells:
            print(f"Warning: no legacy components for {name}", file=sys.stderr)
            continue
        row[current_idx["recipe_components"]] = resolved_cells[name]
        restored += 1

    with OUTPUT_CSV.open("w", newline="") as handle:
        writer = csv.writer(handle, quoting=csv.QUOTE_MINIMAL)
        writer.writerows(current_rows)

    print(f"Restored recipe_components for {restored} base recipes ({skipped} kept existing).")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
