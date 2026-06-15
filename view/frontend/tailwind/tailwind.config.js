/**
 * Tailwind config for Meetanshi_RewardPoints Hyvä module.
 *
 * Registered in app/etc/hyva-themes.json and merged automatically by
 * @hyva-themes/hyva-modules during the theme's Tailwind CSS build.
 *
 * The two remaining style="" attributes in hyva templates are intentional:
 *   - Progress bar width (%)      — dynamic PHP value, cannot be a Tailwind class
 *   - Progress bar background     — dynamic hex from admin config field
 * All other styling uses Tailwind utility classes.
 */
module.exports = {
    content: [
        '../templates/**/*.phtml',
        '../layout/**/*.xml',
    ]
}
