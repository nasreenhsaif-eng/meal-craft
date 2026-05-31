#!/bin/bash

# Clear screen for an ultra-clean workspace view
clear

echo "===================================================="
echo "         MEAL CRAFT ONBOARDING SCREEN BUILDER       "
echo "===================================================="
echo "Select the onboarding component you are developing right now:"
echo "----------------------------------------------------"
echo "1) 👤 Gender Selection Screen Layout"
echo "2) 📐 Height & Weight Interactive Wheels"
echo "3) 🏃 Daily Activity Level Snapping Wheel"
echo "4) 🥦 Food Filter Grid Matrix (with Storybook Configs)"
echo "5) 🩸 Clinical Period & Cycle Tracker (Past/Future Engine)"
echo "6) 📊 Daily Targets Summary Dashboard"
echo "7) ❌ Exit"
echo "----------------------------------------------------"
read -p "Choose a step to prompt [1-7]: " selection

case $selection in
    1)
        TITLE="Gender Selection"
        TARGET="GenderSelection.jsx"
        RULES="Build the profile gender intake step card. Needs Male and Female selection tiles with compact padding. Wrap individual gender icons inside distinct, small square or circular background badges with tight spacing. Center the primary brand-green (#606c4e) 'Continue' button horizontally at the base."
        ;;
    2)
        TITLE="Height & Weight Wheels"
        TARGET="MeasurementWheels.jsx"
        RULES="Build Height and Weight vertical scroll frames. Stick a horizontal capsule segmented unit toggle on the right track (ft/in vs cm, lb vs kg). Use CSS scroll snapping (snap-y, snap-center) displaying exactly 3 values. The center active value must be high-contrast bold text sitting inside a soft neutral cream container with a stationary unit label pinned to its right side. Blend adjacent rows out to 30% opacity with no truncation or trailing ellipsis (...)."
        ;;
    3)
        TITLE="Activity Level Wheel"
        TARGET="ActivityWheel.jsx"
        RULES="Refactor the Activity Level screen to mirror the height picker layout. Create a 3-row single-column vertical scroll snapping track spanning: 'Not Active', 'Somewhat Active', 'Highly Active', and 'Extremely Active'. Position a single text row block directly below the active selection card (w-full text-center) that swaps description strings dynamically ('Moderate movement...', 'Vigorous daily activity') without wrapping lines vertically."
        ;;
    4)
        TITLE="Food Filter Matrix"
        TARGET="FoodFilterMatrix.jsx"
        RULES="Construct the multi-allergen filter under resources/js/Components/meal-system/meal-craft-food-filter/. Render a wrapped flex alignment matrix (flex flex-wrap gap-3 justify-center) displaying exactly 10 pills matching our clean line art icons: Dairy, Gluten-Free, Eggs, Soy, Nightshade, Beans, Nut-Free, Spicy, Shellfish, Other. Activating 'Other' transitions a text entry input into view directly below the matrix utilizing our existing Storybook design parameters. Generate matching .stories.jsx parameter templates."
        ;;
    5)
        TITLE="Period & Cycle Tracker"
        TARGET="PeriodTracker.jsx"
        RULES="Implement the Clinical Period Tracker calendar component. Active logged ranges highlight in solid brand red (#C44F5D) at 100% opacity. Future cycle lines (next 3 months) display in translucent red (#C44F5D at 50% opacity) calculated via our dynamic average cycle duration badge. Plot ovulation dates (nextStart - 14 days) using purple text paired with a tiny solid purple indicator dot centered underneath the date number. Outline the 7-day fertile window in a soft brand purple track mesh at 20% background opacity. Ensure past historical months recursively calculate and render their matching ovulation indicators and fertile tracks cleanly."
        ;;
    6)
        TITLE="Daily Targets Summary"
        TARGET="DailyTargetsSummary.jsx"
        RULES="Generate the final targets page view. Shift our checked indicator pill (✓) to align directly ABOVE the main header block text. Style our macro calculation breakdown cards using low opacity backgrounds of our brand theme tokens: Protein (Cranberry Red accents), Carbs (Primary Green #606c4e), Fat (Brand Yellow). Merge the macro string and percentage values on a single flat line (e.g., 'Protein • 45%') positioned directly underneath the large bold gram readout. Update our main CTA callout button copy to read 'Craft my plan' and stretch its layout dimensions into a comfortable horizontal capsule container to stop words stacking."
        ;;
    7)
        echo "👋 Exiting onboarding workbench utility."
        exit 0
        ;;
    *)
        echo "⚠️ Option invalid. Exiting script."
        exit 1
        ;;
esac

# Save to an isolated scratchpad file that won't disrupt your project routes
echo "$RULES" > .cursor-onboarding-prompt

echo "===================================================="
echo "🎯 Active Workspace Module: $TITLE"
echo "📂 Target File Path: $TARGET"
echo "📝 Layout specification successfully compiled to .cursor-onboarding-prompt"
echo "===================================================="

if command -v code &> /dev/null; then
    code .cursor-onboarding-prompt
else
    echo "Ready! Feed the updated text inside .cursor-onboarding-prompt directly into your Cursor chat console."
fi
