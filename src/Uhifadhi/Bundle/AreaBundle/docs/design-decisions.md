# Area — design decisions

Each deliberate modelling choice, why it was made, and **the trigger that
reopens it**. A decision recorded here is not reopened because somebody
disagrees with it later; it is reopened when its trigger fires.

## Contents

- [The zones uploader is not the storage component yet](#the-zones-uploader-is-not-the-storage-component-yet)
- [A zone may lie outside the area boundary](#a-zone-may-lie-outside-the-area-boundary)
- [Zone overlap tolerance is the area's own setting](#zone-overlap-tolerance-is-the-areas-own-setting)

## The zones uploader is not the storage component yet

The zones configure section draws the shell's own `.upl-*` vocabulary and
drives it with this bundle's `area-upload` controller, rather than the upload
component a module gains with one Twig line — because that component lives in
`uhifadhi/storage-module`, and the core cannot require a capability module.
**Reopen when** the visual upload component and its controller move into the
shell as `render_upload()`, with storage-module implementing the persisted-file
target on top of it: this section then drops its own markup and calls that.

## A zone may lie outside the area boundary

Containment was a refusal until the first real import: a gazetted edge and an
operational subdivision are drawn by different people from different sources,
and seven of eleven sectors fell partly outside a boundary that was itself only
approximate. So a feature that reaches past the line **arrives**, and its row
says how far — "extends 412 km² beyond the boundary" — measured with a
per-million tolerance so a ring rounded off the boundary's own edge says
nothing. A ring rounded off that edge used to be refused outright, which is the
narrower version of the same mistake.

The consequence is arithmetic: once a zone may lie outside, "zoned of area" can
exceed a hundred percent. Every share on the configure page is therefore
computed against the **union** of the boundary and the zone set —
`ZoneRepository::stGroundKm2()` — which counts each piece of ground this area
accounts for exactly once. `AreaRegister::areaKm2()` still means the boundary
alone, because that is what the settings record and the identity band are
about.

**Reopen when** an installation needs a zone set that is provably a partition
of its boundary — a legal subdivision rather than an operational one. That is a
different guarantee, and it belongs in a per-area rule beside the overlap
tolerance rather than in the importer.

## Zone overlap tolerance is the area's own setting

Sibling zones still may not share interior, but "share" is now measured rather
than detected: two rings digitised by hand share metres of edge that were meant
to touch, and refusing a scheme over that refuses it over arithmetic.
`AreaOfInterest::$zoneOverlapTolerancePct` (nullable, default 1.0, capped at 10)
says how much of the **smaller** ring may be shared before it is an overlap;
anything under one square kilometre is a sliver whatever the rings are.

It is the area's because how carefully a scheme was drawn is a fact about an
installation's survey, not about this product. It is nullable because writing
today's default into every row would freeze this release's number into the data
and make changing it a migration. An accepted sliver is **stored as it
arrived** — neither ring is clipped — and the ground the two share is answered
for by the same deterministic tie-break that already answers which zone a point
on a shared edge is in: lowest name, then lowest id.

**Reopen when** a module needs to state area totals by summing zone areas. With
slivers accepted, the sum of the zones slightly exceeds the ground they cover,
and such a module needs `stGroundKm2()` rather than a sum.
