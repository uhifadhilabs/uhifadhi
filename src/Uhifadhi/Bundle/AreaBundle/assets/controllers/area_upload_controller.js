/*
 * This file is part of the Uhifadhi core.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

import { Controller } from '@hotwired/stimulus';

/*
 * THE DROP ZONE SAYS WHICH FILE IT IS HOLDING.
 *
 * A file input styled as a plate is the one control that can look identical
 * before and after it has been used, and "Drop your boundary here" still sitting
 * there after a choice is how somebody comes to submit an empty form twice. So
 * the label is replaced with the chosen filename the moment there is one.
 *
 * PROGRESSIVE, NOT LOAD-BEARING. The plate is a <label> wrapping a real file
 * input, so clicking it opens the picker and the form submits with or without
 * this file ever being fetched. Everything here is decoration over markup that
 * already works — which is why the tests assert on the input and not on this.
 *
 * DROP IS HANDLED BECAUSE THE PLATE PROMISES IT. The caption says "drop your
 * boundary here"; a plate that says that and then opens the file in a new tab
 * when you do is worse than a plain button.
 */
export default class extends Controller {
    static targets = ['drop', 'file', 'label'];

    connect() {
        this.defaultLabel = this.hasLabelTarget ? this.labelTarget.textContent : '';
    }

    chosen() {
        const file = this.fileTarget.files && this.fileTarget.files[0];
        if (this.hasLabelTarget) {
            this.labelTarget.textContent = file ? file.name : this.defaultLabel;
        }
        this.dropTarget.classList.toggle('has-file', Boolean(file));
    }

    /* The browser's default for a dropped file is to navigate to it. */
    over(event) {
        event.preventDefault();
        this.dropTarget.classList.add('over');
    }

    leave() {
        this.dropTarget.classList.remove('over');
    }

    drop(event) {
        event.preventDefault();
        this.dropTarget.classList.remove('over');
        if (event.dataTransfer && event.dataTransfer.files.length > 0) {
            this.fileTarget.files = event.dataTransfer.files;
            this.chosen();
        }
    }
}
