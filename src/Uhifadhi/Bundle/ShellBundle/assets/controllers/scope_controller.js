import { Controller } from '@hotwired/stimulus';

/*
 * THE SCOPE CONTROL SUBMITS WHEN IT CHANGES.
 *
 * Changing which slice you are looking at is a NAVIGATION, and a navigation
 * that needs a second click on a button beside the select is a navigation
 * people forget to make — they change the dropdown, read the old page and
 * believe it.
 *
 * AND IT WORKS WITHOUT THIS. The control is a real GET form: with no script
 * it submits on Enter and the page reloads at the new address, which is why
 * there is no button to hide and nothing to restore. All this adds is the
 * click that would otherwise be a keystroke.
 */
export default class extends Controller {
    go() {
        this.element.requestSubmit();
    }
}
