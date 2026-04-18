import { createElement } from "react";
import { createRoot } from "react-dom/client";
import { IngredientAnalyzerApp } from "./ingredient-analyzer/IngredientAnalyzerApp.jsx";

const el = document.getElementById("ingredient-analyzer-root");

if (el) {
    createRoot(el).render(
        createElement(IngredientAnalyzerApp, {
            csrfToken: el.dataset.csrf ?? "",
            endpoint: el.dataset.endpoint ?? "",
            statusBase: el.dataset.statusBase ?? "",
            saveEndpoint: el.dataset.saveEndpoint ?? "",
        }),
    );
}
