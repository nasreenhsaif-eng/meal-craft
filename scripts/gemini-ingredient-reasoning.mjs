#!/usr/bin/env node
/**
 * Optional Gemini path using @google/generative-ai (GEMINI_MODEL, default gemini-2.5-flash).
 * Enable with GEMINI_USE_NODE_SDK=true in .env
 *
 * Stdin: JSON {"input":"user phrase"}
 * Stdout: JSON model response text (object) or {"error":"..."}
 */
import { readFileSync } from "node:fs";
import { GoogleGenerativeAI } from "@google/generative-ai";

const key = process.env.GOOGLE_GEMINI_KEY;

if (!key) {
    process.stdout.write(JSON.stringify({ error: "missing GOOGLE_GEMINI_KEY" }));
    process.exit(0);
}

let payload;

try {
    payload = JSON.parse(readFileSync(0, "utf-8"));
} catch {
    process.stdout.write(JSON.stringify({ error: "invalid stdin json" }));
    process.exit(0);
}

const input = typeof payload.input === "string" ? payload.input.trim() : "";

if (input === "") {
    process.stdout.write(JSON.stringify({ error: "empty input" }));
    process.exit(0);
}

const system = `You are a functional nutrition assistant. Standardize the user's food description into a simple food name and an estimated quantity in grams. Provide a one-sentence functional tip about gut health or anti-inflammatory properties.

If the user names a common animal protein (e.g. chicken breast, steak, ground beef, salmon), set standardized_name in USDA-style phrasing for FoodData Central: commodity first, then cut or form, then qualifiers—e.g. "Chicken, breast, meat only, raw" or "Beef, grass-fed, ground".

Detect soaking/soaked/sprouted mentions.

Respond with ONLY valid JSON:
{"standardized_name":"string","quantity_g":number,"functional_tip":"string","was_soaked_mentioned":boolean,"soaking_benefit_note":"string"}

If soaking was not mentioned, was_soaked_mentioned=false and soaking_benefit_note="".
If mentioned, soaking_benefit_note explains phytic acid reduction and nutrient absorption in 1-2 sentences.`;

const genAI = new GoogleGenerativeAI(key);
const modelId = process.env.GEMINI_MODEL?.trim() || "gemini-2.5-flash";
const model = genAI.getGenerativeModel({
    model: modelId,
    systemInstruction: system,
});

try {
    const result = await model.generateContent({
        contents: [{ role: "user", parts: [{ text: input }] }],
        generationConfig: {
            responseMimeType: "application/json",
            temperature: 0.35,
        },
    });

    process.stdout.write(result.response.text());
} catch (e) {
    process.stdout.write(
        JSON.stringify({ error: e instanceof Error ? e.message : "gemini_error" }),
    );
}
