# Area — design decisions

Each deliberate modelling choice, why it was made, and **the trigger that
reopens it**. A decision recorded here is not reopened because somebody
disagrees with it later; it is reopened when its trigger fires.

## Contents

- [The zones uploader is not the storage component yet](#the-zones-uploader-is-not-the-storage-component-yet)

## The zones uploader is not the storage component yet

The zones configure section draws the shell's own `.upl-*` vocabulary and
drives it with this bundle's `area-upload` controller, rather than the upload
component a module gains with one Twig line — because that component lives in
`uhifadhi/storage-module`, and the core cannot require a capability module.
**Reopen when** the visual upload component and its controller move into the
shell as `render_upload()`, with storage-module implementing the persisted-file
target on top of it: this section then drops its own markup and calls that.
