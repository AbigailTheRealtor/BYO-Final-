/*
 |-----------------------------------------------------------------------------
 | Runtime capability probe
 |-----------------------------------------------------------------------------
 |
 | MapLibre requires WebGL2. Some containers — this project's Replit sandbox among
 | them — expose none, even with SwiftShader present and every documented flag
 | passed. The specs that genuinely need a rendered canvas therefore skip rather
 | than fail there, while everything that can be verified without pixels still
 | runs and still counts.
 |
 | A SKIP MUST NEVER BE SILENT. `test.skip()` records the reason, so a run in a
 | GL-less container reports plainly which coverage did not execute rather than
 | reporting a smaller green number that looks like success.
 */

async function hasWebgl2(page) {
    return page.evaluate(() => {
        try {
            const canvas = document.createElement('canvas');
            return !!canvas.getContext('webgl2');
        } catch (e) {
            return false;
        }
    });
}

module.exports = { hasWebgl2 };
