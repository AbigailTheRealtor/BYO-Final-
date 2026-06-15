---
name: Livewire third-party map/widget stable-ID pattern
description: How to embed Google Maps (or any third-party DOM widget) inside a Livewire component without re-renders destroying the instance.
---

# Livewire Third-Party Widget Stable-ID Pattern

## The Rule
Never use `uniqid()` / random IDs for DOM containers that hold third-party widgets (Google Maps, charts, rich-text editors) inside Livewire components. Use a stable, deterministic ID AND add `wire:ignore` to the container element.

**Why:** Livewire re-renders fire on every `wire:click` / `wire:model` update. Each PHP re-render evaluates `uniqid()` freshly, producing a new container ID. Livewire morphs the old element → new element (different ID). The original JS IIFE closure has the old ID hardcoded from compile time; `getElementById(oldId)` returns null → widget never initializes. `wire:ignore` tells Livewire to leave the element's children alone, preserving the widget's injected DOM.

**How to apply:**
1. Replace `$panelId = 'prefix-' . uniqid()` with `$panelId = $panelId ?? 'prefix-panel'` (accept a passed-in stable ID).
2. Pass the stable ID at the `@include` call site: `@include('partial', ['panelId' => 'my-stable-id'])`.
3. Add `wire:ignore` to the container div: `<div id="{{ $panelId }}" wire:ignore></div>`.
4. The JS closure referencing `getElementById('my-stable-id')` now survives Livewire re-renders.

**Applied to:** `map-input.blade.php` — buyer uses `ldna-map-buyer`, tenant uses `ldna-map-tenant`.
