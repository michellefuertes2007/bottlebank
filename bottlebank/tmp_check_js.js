const fs = require('fs');
const html = fs.readFileSync('deposit.php', 'utf8');
const scriptBlocks = [...html.matchAll(/<script[^>]*>([\s\S]*?)<\/script>/gi)];
let foundError = false;
scriptBlocks.forEach((m, idx) => {
  const script = m[1];
  const previewStart = script.trim().split('\n').slice(0, 5).join('\n');
  const previewEnd = script.trim().split('\n').slice(-5).join('\n');
  try {
    new Function(script);
    console.log(`Script block #${idx + 1}: OK`);
  } catch (e) {
    console.error(`Script block #${idx + 1}: Syntax error: ${e.message}`);
    console.error('--- START PREVIEW ---');
    console.error(previewStart);
    console.error('--- END PREVIEW ---');
    console.error('--- END PREVIEW (tail) ---');
    console.error(previewEnd);
    foundError = true;
  }
});
if (foundError) process.exit(1);
