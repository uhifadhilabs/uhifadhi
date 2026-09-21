import { Controller } from '@hotwired/stimulus';

/*
 * FOLD ALL / OPEN ALL — the two buttons the grants matrix draws above itself.
 *
 * THE FOLDS THEMSELVES NEED NO SCRIPT. They are native <details>, so a reader
 * with no JavaScript still opens and closes every group one at a time; this
 * controller only adds the pair of shortcuts the design draws, and the buttons
 * are rendered only where it can run.
 *
 * IT OWNS NO STATE. There is nothing to remember between the two actions — the
 * open flag lives on each <details>, where the browser keeps it — so asking the
 * elements is always right and a cached list would go stale the moment a group
 * is toggled by hand.
 */
export default class extends Controller {
    static targets = ['fold'];

    /** Close every group. */
    foldAll() {
        this.#set(false);
    }

    /** Open every group, including the ones that grant nothing. */
    openAll() {
        this.#set(true);
    }

    #set(open) {
        this.foldTargets.forEach((fold) => {
            fold.open = open;
        });
    }
}
