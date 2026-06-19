---
name: ldna DrawingManager race + definitive fix
description: DrawingManager race condition that caused Draw Polygon/Circle to silently fail; resolved by removing DrawingManager entirely and using custom click-based drawing.
---

## The Rule
**Do not use Google Maps DrawingManager for Buyer/Tenant location DNA map.** Use custom click-based drawing via map.addListener('click'). The DrawingManager has an unresolvable race condition in the Livewire/Bootstrap tab context.

## Why
The Google Maps drawing library namespace (`google.maps.drawing`) can exist before the `DrawingManager` constructor is populated. Two-path init with lazy creation (`ldnaSetDrawMode` retrying on null) does not reliably resolve it because the retry period is unpredictable and users get no feedback. After two sessions of attempted DrawingManager fixes the issue persisted.

## Definitive Architecture (no DrawingManager)
- `ldnaTryInit()` only checks `typeof google.maps.Map === 'function'` — no drawing library dependency
- **Polygon**: `ldnaStartDrawPolygon()` attaches `map.addListener('click', ...)` — each click places a vertex Marker and updates a Polyline preview; "Finish Polygon" HUD button calls `ldnaFinishDrawing()` which creates `google.maps.Polygon` from captured vertices
- **Circle**: `ldnaStartDrawCircle()` — first click sets center (Marker shown), second click computes radius via Haversine (no geometry library), creates `google.maps.Circle`; `mousemove` shows transparent preview while positioning
- `ldnaStopDrawing()` removes all temp Markers/Polylines/listeners; called by Cancel and by ClearDrawings
- HUD (`ldna-drawing-hud`) is a `<div style="display:none">` that shows status text + Finish/Cancel buttons during active draw mode

## How to Apply
If Draw tools need to be added or modified: never add DrawingManager back. Extend the custom click-based system. The relevant functions are `ldnaStartDrawPolygon`, `ldnaStartDrawCircle`, `ldnaStopDrawing`, `ldnaFinishDrawing`, `ldnaCancelDrawing`, all in `resources/views/partials/location-dna/map-input.blade.php`.
