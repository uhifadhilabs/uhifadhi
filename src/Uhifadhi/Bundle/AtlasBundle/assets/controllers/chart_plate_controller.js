import { Controller } from '@hotwired/stimulus';

/*
 * A CHART'S COLOURS ARE TOKENS UNTIL THE MOMENT IT IS DRAWN.
 *
 * Chart.js paints onto a canvas, and a canvas is not the document: a
 * `var(--cat-3)` written into a dataset is not resolved by the browser the way
 * it would be on any element, it is handed to the 2D context as a string it
 * cannot parse, and the series draws as nothing. So the colours cross the wire
 * as TOKENS — which is what lets the palette turn over with the theme, and what
 * keeps a chart's third series the same mark as the third zone on the plate
 * beside it — and this resolves them against the element the chart is mounted
 * on, at the last possible moment.
 *
 * AT MOUNT, AND AGAIN WHEN THE THEME FLIPS. `getComputedStyle` answers with
 * whatever `--cat-3` means right now, and after dark it means something else;
 * a chart that resolved once would be a chart drawn in yesterday's palette
 * until the page was reloaded. The same MutationObserver the map plate keeps,
 * for the same reason.
 *
 * IT CHANGES NOTHING ELSE. Every other option the chart carries is the
 * builder's, untouched — this walks the built configuration, swaps token
 * strings for values, and hands it back.
 *
 * @see https://symfony.com/bundles/ux-chartjs/current/index.html — `chartjs:pre-connect`
 */
const TOKEN = /^var\(\s*(--[a-zA-Z0-9-]+)\s*\)$/;

/** The properties a colour can reach a dataset or a scale through. */
const PAINTED = ['backgroundColor', 'borderColor', 'color', 'pointBackgroundColor', 'pointBorderColor'];

export default class extends Controller {
    connect() {
        this.swatches = new Map();

        this.onPreConnect = (event) => this.paint(event.detail.config);
        this.element.addEventListener('chartjs:pre-connect', this.onPreConnect);

        /* The theme is a class on <html>, and it is put there before the first
           paint by the shell's own inline script — so what this catches is a
           later flip, made with the toggle while a chart is on screen. */
        this.onThemeFlip = () => this.repaint();
        this.themeWatch = new MutationObserver(this.onThemeFlip);
        this.themeWatch.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

        this.onConnect = (event) => { this.chart = event.detail.chart; };
        this.element.addEventListener('chartjs:connect', this.onConnect);
    }

    disconnect() {
        this.element.removeEventListener('chartjs:pre-connect', this.onPreConnect);
        this.element.removeEventListener('chartjs:connect', this.onConnect);
        this.themeWatch?.disconnect();
        this.themeWatch = null;
    }

    /** Every painted property of every dataset, resolved in place. */
    paint(config) {
        for (const dataset of config?.data?.datasets ?? []) {
            for (const property of PAINTED) {
                if (property in dataset) {
                    dataset[property] = this.resolve(dataset[property]);
                }
            }
        }
    }

    /**
     * THE SAME CHART, IN THE PALETTE THAT IS ON NOW. The tokens are gone from
     * the built configuration by the time this runs — they were resolved at
     * mount — so the source of truth is the ORIGINAL config Chart.js keeps,
     * and what is re-read is the token cache, cleared so every value is asked
     * for again.
     */
    repaint() {
        if (!this.chart) {
            return;
        }

        this.swatches.clear();
        this.paint(this.chart.config._config ?? this.chart.config);
        this.chart.update('none');
    }

    /**
     * A token's value, or whatever was handed over where it is not one.
     *
     * Cached per element: a stacked chart asks for the same six tokens once a
     * dataset, and `getComputedStyle` is a layout read.
     */
    resolve(value) {
        if ('string' !== typeof value) {
            return value;
        }

        const token = TOKEN.exec(value);
        if (!token) {
            return value;
        }

        if (!this.swatches.has(token[1])) {
            this.swatches.set(token[1], getComputedStyle(this.element).getPropertyValue(token[1]).trim() || value);
        }

        return this.swatches.get(token[1]);
    }
}
