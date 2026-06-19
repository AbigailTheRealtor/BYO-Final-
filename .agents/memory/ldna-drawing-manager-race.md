---
name: ldna DrawingManager race + lazy init pattern
description: Why !google.maps.drawing namespace check is not enough; how ldnaSetDrawMode lazily creates DrawingManager to handle race conditions.
---

## The Rule
Always check `typeof google.maps.drawing.DrawingManager === 'function'` — not just `!google.maps.drawing` — before attempting to create a DrawingManager.

In `ldnaSetDrawMode()`: if `ldnaDrawingManager` is null but `ldnaMap` is set, call `ldnaCreateDrawingManager()` (try-catch wrapped) before setting the draw mode. This is the lazy-init path for the race condition.

## Why
The Google Maps JS API loads libraries semi-asynchronously. `google.maps.drawing` (the namespace object) can exist before `google.maps.drawing.DrawingManager` (the constructor) is populated. When `ldnaTryInit()` only checks `!google.maps.drawing`, it may proceed into `ldnaInitMap()` where `new google.maps.drawing.DrawingManager()` throws a TypeError. The guard `ldnaMapInitialized = true` is set before the DrawingManager call, so it never retries — `ldnaMap` gets set, `ldnaDrawingManager` stays null forever.

**Symptom**: Radius searches (which only need `ldnaMap`) work fine; Draw Polygon and Draw Circle silently do nothing.

## How to Apply
- `ldnaTryInit()`: two-path check — if `DrawingManager` constructor is not a function but Maps API is otherwise ready, init the map anyway (radius + autocomplete still work); DrawingManager will be created lazily.
- `ldnaCreateDrawingManager()`: standalone function, try-catch, returns null on any failure.
- `ldnaSetDrawMode(mode)`: checks `ldnaDrawingManager` null → calls `ldnaCreateDrawingManager()` → retries after 600ms if still null.
- Add visual active-state on Draw buttons so the user sees confirmation that drawing mode was activated.
