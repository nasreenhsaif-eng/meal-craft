import MealCraftLogo from './Atoms/Logo/MealCraftLogo.jsx';

/**
 * Full-viewport pre-login intro — Motion identity splash (`marketing-animated`).
 *
 * @param {{ onComplete?: () => void }} props
 */
export function AnimatedIntro({ onComplete }) {
    return (
        <MealCraftLogo variant="marketing-animated" presentation="splash" onSplashComplete={onComplete} alt="Meal Craft" />
    );
}
