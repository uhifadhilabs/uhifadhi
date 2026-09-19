# Changelog — AreaBundle

## Contents

- [1.0.0](#100)

## 1.0.0

Not released yet.

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
 * the duty endpoints a handset writes through, and the station sections a
   module contributes to a post's record and its configure card
 * the answer to the platform's area contract, so nothing has to be written by
   hand to point a module's record at an area
 * the area overview, composed from what the modules an area runs contribute:
   now-tiles, attention items, map layers, pulse events and copy
 * the areas register, the area's own map plate, and the widget surface behind
   the landing page's layouts
 * `GET /api/areas/mine`: the areas an account may work in, with their size,
   roster and simplified boundary — the cache a field client fills at sign-in
 * the Areas section in the sidebar and the tab strip above every area page
