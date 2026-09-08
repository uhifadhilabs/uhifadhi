/*
 * The platform's basemap sources, defined ONCE so every map — the host's area
 * maps and every module's plates — shows the same imagery. A layer must look
 * the same wherever it is drawn (the map-legend rule), and that starts with the
 * ground it is drawn on.
 *
 * WHICH IMAGERY IS A DEPLOYMENT'S DECISION, NOT THIS FILE'S. Hardcoding
 * Google's would mean every map on every page opening by asking Google's Map
 * Tiles API for a session token, and for an EEA-billed account Google answers
 * that question 403:
 *
 *     "satellite tiles and 3D tiles are not available for your account and
 *      region"
 *
 * The whole product therefore ran on the understudy while filling the console
 * with refusals. The choice is now configuration (the atlas, the
 * `map.satellite` tree), published to the document as one JSON object on
 * <body data-map-satellite> by {{ map_basemap_attributes() }}, and read here:
 *
 *   esri    — keyless World Imagery. The default, and the understudy for
 *             everything else. No key, no session, no request that can be
 *             refused.
 *   google  — the Map Tiles API. Serves XYZ tiles only against a session token
 *             from a createSession call, "valid for two weeks from its creation
 *             time":
 *               https://developers.google.com/maps/documentation/tile/session_tokens
 *               https://developers.google.com/maps/documentation/tile/roadmap
 *             The key travels inside every tile URL, so it is public by nature
 *             and must be HTTP-referrer restricted at Google.
 *   custom  — the deployment's own XYZ/WMTS template and the attribution its
 *             licence requires. Drawn directly; nothing is asked of anyone.
 *
 * NO KEY, NO PROBLEM: an unconfigured, failed or refused Google session leaves
 * the map on Esri. A map must never be blank for want of a key.
 *
 * AND A REFUSAL IS AN ANSWER. Caching only SUCCESS would mean a settled refusal
 * re-earned by every map on the page and again by every remount: a two-map
 * dashboard firing two createSession calls. Both answers are remembered, and the
 * attempt itself is shared — one question per document, whatever the answer
 * turns out to be.
 */

const CREATE_SESSION_URL = 'https://tile.googleapis.com/v1/createSession';
const TILE_URL = 'https://tile.googleapis.com/v1/2dtiles/{z}/{x}/{y}';
const SESSION_STORAGE_KEY = 'uhifadhi.google_tile_session';
/* Renew a day before Google expires the token, never trusting it to the wire. */
const EXPIRY_MARGIN_MS = 86400 * 1000;
/* How long a refusal is taken at its word. Long enough that a wall display does
 * not re-ask all night, short enough that an account which gains satellite is
 * picked up the same day rather than needing a new tab. */
const NEGATIVE_TTL_MS = 3600 * 1000;

/* The one createSession attempt for this document, shared by every map on it.
 * A dashboard with eight map widgets asks once, and a remount — the poll cycle,
 * a Turbo visit — awaits the answer already given rather than asking again. */
let pendingSession = null;

export const OSM_TILES = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
export const OSM_ATTRIBUTION = '© OpenStreetMap contributors';

/* The keyless imagery: the default provider, and the ground every other
 * provider stands on until it has proved it can serve a tile. */
export const ESRI_TILES =
    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
export const ESRI_ATTRIBUTION = 'Esri, Maxar, Earthstar Geographics';

/* Google requires its attribution to be shown alongside its imagery. */
export const GOOGLE_ATTRIBUTION = '© Google';

export const PROVIDER_ESRI = 'esri';
export const PROVIDER_GOOGLE = 'google';
export const PROVIDER_CUSTOM = 'custom';

const DEFAULT_SOURCE = { provider: PROVIDER_ESRI, maxZoom: 19 };

/**
 * The satellite source this deployment configured, read from the <body>.
 *
 * A document with no attribute is not an error and not a warning: it is a
 * deployment that never configured one, which means Esri — the same answer the
 * server would have given. The map draws either way.
 */
export function satelliteSource() {
    const raw = document.body?.dataset?.mapSatellite;
    if (!raw) {
        return DEFAULT_SOURCE;
    }
    try {
        const parsed = JSON.parse(raw);

        return parsed && typeof parsed.provider === 'string' ? { ...DEFAULT_SOURCE, ...parsed } : DEFAULT_SOURCE;
    } catch {
        return DEFAULT_SOURCE; // malformed attribute: draw Esri rather than nothing
    }
}

/**
 * A tile URL template for Google's 2D satellite tiles, or null when there is no
 * key or the session could not be created.
 */
export async function googleTileTemplate(key) {
    if (!key) {
        return null;
    }

    const session = await sessionToken(key);

    return session ? `${TILE_URL}?session=${encodeURIComponent(session)}&key=${encodeURIComponent(key)}` : null;
}

/**
 * The session token, or null where Google will not give one. Asked at most once
 * per document: the first caller starts the attempt and every later one awaits
 * the same promise.
 */
function sessionToken(key) {
    pendingSession ??= (async () => {
        const cached = readCachedSession();
        // A cached refusal is null, and null is an answer — only `undefined`
        // means nothing is known yet and the question is worth asking.
        if (undefined !== cached) {
            return cached;
        }

        const granted = await requestSession(key);
        if (granted) {
            cacheSession(granted.session, granted.expiresAt);

            return granted.session;
        }
        cacheSession(null, Date.now() + NEGATIVE_TTL_MS);

        return null;
    })();

    return pendingSession;
}

/** `{session, expiresAt}` when Google grants one, null for every way it does not. */
async function requestSession(key) {
    try {
        // Required body fields per the createSession reference; mapType picks the
        // satellite imagery.
        const response = await fetch(`${CREATE_SESSION_URL}?key=${encodeURIComponent(key)}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mapType: 'satellite', language: 'en-US', region: 'US' }),
        });
        if (!response.ok) {
            return null; // 403 for an EEA-billed account: settled, and now remembered
        }
        const payload = await response.json();
        if (typeof payload?.session !== 'string' || payload.session === '') {
            return null;
        }

        // `expiry` is seconds-since-epoch, as a string.
        return { session: payload.session, expiresAt: Number(payload.expiry) * 1000 - EXPIRY_MARGIN_MS };
    } catch {
        return null; // offline, blocked key, CORS — fall back, never break
    }
}

/**
 * A token, null for a remembered refusal, or undefined where nothing is known.
 * Three answers, because collapsing the last two is precisely how a remembered
 * refusal turns back into a request on the next mount.
 */
function readCachedSession() {
    try {
        const raw = window.sessionStorage?.getItem(SESSION_STORAGE_KEY);
        if (!raw) {
            return undefined; // nothing known
        }
        const { session, expiresAt } = JSON.parse(raw);
        if (!(Date.now() < expiresAt)) {
            return undefined; // nothing known: it lapsed, so ask again
        }

        return typeof session === 'string' ? session : null;
    } catch {
        return undefined; // nothing known
    }
}

function cacheSession(session, expiresAt) {
    try {
        window.sessionStorage?.setItem(SESSION_STORAGE_KEY, JSON.stringify({ session, expiresAt }));
    } catch {
        /* private mode / storage disabled: just make the call again next time */
    }
}

/**
 * The street base layer. One definition, every map.
 */
export function streetLayer(L, { maxZoom = 19 } = {}) {
    return L.tileLayer(OSM_TILES, { maxZoom, attribution: OSM_ATTRIBUTION });
}

/**
 * The satellite base layer, built for whichever provider this deployment
 * configured.
 *
 * A CUSTOM source is drawn directly — the deployment vouched for it, and there
 * is nothing to negotiate. Everything else starts on the keyless Esri imagery so
 * the map is never blank, and a GOOGLE deployment then upgrades itself (tiles
 * and attribution together) as soon as its session resolves. An esri
 * deployment, or a google one whose session is refused, simply stays where it
 * started.
 *
 * `map` is passed in so the attribution can be swapped through Leaflet's public
 * control API rather than reaching into the layer's private `_map`.
 */
export function satelliteLayer(L, map, options = {}) {
    const source = satelliteSource();
    const maxZoom = options.maxZoom ?? source.maxZoom ?? 19;

    if (PROVIDER_CUSTOM === source.provider && source.urlTemplate) {
        return L.tileLayer(source.urlTemplate, { maxZoom, attribution: source.attribution ?? '' });
    }

    const layer = L.tileLayer(ESRI_TILES, { maxZoom, attribution: ESRI_ATTRIBUTION });

    if (PROVIDER_GOOGLE !== source.provider) {
        return layer;
    }

    googleTileTemplate(source.key).then((template) => {
        if (!template) {
            return;
        }
        layer.options.attribution = GOOGLE_ATTRIBUTION;
        if (map?.attributionControl) {
            map.attributionControl.removeAttribution(ESRI_ATTRIBUTION);
            // Leaflet only shows the attribution of layers currently on the map.
            if (map.hasLayer(layer)) {
                map.attributionControl.addAttribution(GOOGLE_ATTRIBUTION);
            }
        }
        layer.setUrl(template);
    });

    return layer;
}