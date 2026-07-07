<?php
$src = file_get_contents('docs/launch-audits/Create-Offer-and-Hire-Agent-Edits-June-28-2026.md');
$lines = explode("\n", $src);

// Phase section boundaries: capture current phase label as we walk.
$phase = null; $items = [];
foreach ($lines as $ln) {
    if (preg_match('/^### PHASE (\d+) — (.+?)(?:\s+\(Source|\s+⚠|$)/', $ln, $m)) {
        $phase = ['num'=>(int)$m[1], 'title'=>trim($m[2])];
        continue;
    }
    // table data rows: | **ID** | Item | Priority | Roles | Flows | Status |
    if ($phase && preg_match('/^\|\s*\*\*(A\d+\.\d+|B\d+\.\d+|C\d+|F\.\d+)\*\*\s*\|(.+)\|\s*$/', $ln, $m)) {
        $id = $m[1];
        $cells = array_map('trim', explode('|', $m[2]));
        // Expect: Item, Priority, Roles, Flows, Status  (F.1 table has fewer cols)
        if (count($cells) >= 5) {
            [$item,$prio,$roles,$flows,$status] = array_slice($cells,0,5);
        } elseif (count($cells) >= 2) { // F.1: Item, Priority, Status
            $item=$cells[0]; $prio=$cells[1]; $roles=''; $flows=''; $status=$cells[2]??'';
        } else continue;
        // status glyph
        $g = 'todo';
        if (strpos($status,'✅')!==false) $g='done';
        elseif (strpos($status,'🟡')!==false) $g='partial';
        elseif (strpos($status,'⬜')!==false) $g='todo';
        $items[] = compact('id','phase','item','prio','roles','flows','status','g');
    }
}
echo json_encode($items, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
