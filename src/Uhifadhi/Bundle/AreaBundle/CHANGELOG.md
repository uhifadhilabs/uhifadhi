# Changelog — AreaBundle

## Contents

- [1.0.0](#100)

## 1.0.0

Not released yet.

 * the area answers `LivePositionsInterface`: the latest fix of everybody on an
   open watch, with the distance to the post measured by PostGIS rather than
   from degrees in PHP — a module draws "where everybody is" without reading
   `duty_position` across a package boundary or deriving a second answer to
   "at post, verified"
 * the station and zone captions keep the leading their design shorthands set
   (`.fldlab`, `.stcardlead`, `.stcode`, `.stghm`, `.stnone`, `.stpin`)
 * the areas row wears the house MAP mark, as the design draws it, and the
   areas under it wear none: a glyph repeated down a branch reads as a second
   kind of thing rather than the same thing twice
 * the area answers `Area\StationDirectoryInterface`: every station and who
   stands at each, across every area at once, in two queries. Every per-area
   read it already published is scoped to one area, and a reader who has to
   open four areas to count the postings cannot count them at all.

 * **BREAKING for an installation** — the spatial base and the geometry types
   now come from `utafitilabs/postgis-bundle` instead of
   `fundistadi/postgis-bundle`. An installation replaces the bundle class in
   `config/bundles.php` with `UtafitiLabs\PostGISBundle\UtafitiLabsPostGISBundle`
   and renames the configuration root key `fundi_stadi_post_gis` to
   `utafiti_labs_post_gis`; both packages register the same DBAL type names, so
   the old one has to be removed, not merely superseded
 * the area: its identity, its gazetted facts and its boundary as a PostGIS
   multipolygon, with the zones inside it held to a no-shared-interior invariant
 * a whole zoning scheme from one GeoJSON FeatureCollection: names read from
   whichever property the export used, altitudes and every other property read
   past and then named in the summary, and a refusal that names the offending
   zone for a projected coordinate system, an overlap, a polygon outside the
   area boundary, a duplicate name or a missing geometry
 * the provenance of an imported scheme — the file's name, the moment, the
   person, the count and the property the names came out of — stored beside the
   geometry, while the uploaded file itself is read and let go
 * the day a ranger reports: the check-in a handset claims, the words an area
   lets them claim it with, the corrections appended to it and the duty pings —
   with `verified` and `unverified` derived on every read and never stored
 * the duty endpoints a handset writes through and the two reads its Duty tab
   lives on — the month, the words the area publishes and the ping interval it
   set, and the posts with the catchment each of them carries
 * the station sections a module contributes to a post's record and its
   configure card
 * the answer to the platform's area contract, so nothing has to be written by
   hand to point a module's record at an area
 * the area overview, composed from what the modules an area runs contribute:
   now-tiles, attention items, map layers, pulse events and copy
 * the areas register, the area's own map plate, and the widget surface behind
   the landing page's layouts
 * `GET /api/areas/mine`: the areas an account may work in, with their size,
   roster and simplified boundary — the cache a field client fills at sign-in
 * the Areas section in the sidebar and the tab strip above every area page
