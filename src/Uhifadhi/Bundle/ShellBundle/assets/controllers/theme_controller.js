import { Controller } from '@hotwired/stimulus';

/*
 * The theme toggle, and ONLY the toggle.
 *
 * Applying the theme is not this controller's job and must not become it: the
 * document resolves the choice in an inline script in the head, before the
 * first paint. A controller connects after the first frame, so a visitor who
 * chose dark would be shown a white page first — the defect the pre-paint
 * script exists to prevent.
 *
 * The key is the shell's published one (Uhifadhi\Bundle\ShellBundle\Service\Theme::CHOICE_KEY):
 * the head script reads it, this writes it, and neither invents its own name.
 *
 * The class is the contract's dark selector (LayoutContract::DARK_SELECTOR), on
 * <html>, because every module stylesheet in the platform writes `html.dark`.
 */
export default class extends Controller {
    toggle() {
        const isDark = document.documentElement.classList.toggle('dark');
        try {
            localStorage.setItem('shell-theme', isDark ? 'dark' : 'light');
        } catch (e) {
            // Storage unavailable — the toggle still works for this page view.
        }
    }
}
