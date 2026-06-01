/**
 * @param {string} birthdate ISO date string (YYYY-MM-DD)
 * @returns {number}
 */
export function calculateAgeFromBirthdate(birthdate) {
    if (!birthdate) {
        return 0;
    }

    const born = new Date(`${birthdate}T00:00:00`);
    const today = new Date();
    let age = today.getFullYear() - born.getFullYear();
    const monthDelta = today.getMonth() - born.getMonth();

    if (monthDelta < 0 || (monthDelta === 0 && today.getDate() < born.getDate())) {
        age -= 1;
    }

    return Math.max(0, age);
}
