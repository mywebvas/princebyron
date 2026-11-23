const fs = require('fs');
const path = require('path');

const targetDirs = [
  'core/resources/css',
  'core/resources/js',
  'core/resources/views',
  'core/app/Filament',
  'core/app/Providers/Filament'
];

const colorRegex = /#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})\b|rgba?\([^)]+\)|hsla?\([^)]+\)/gi;

// Exceptions allowed by design:
// 1. core/resources/css/tokens.css (the auto-generated file from config/tokens.php)
const allowedFiles = [
  path.normalize('core/resources/css/tokens.css')
];

let totalScanned = 0;
let findings = [];

function scanDir(dir) {
  if (!fs.existsSync(dir)) return;
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  for (const entry of entries) {
    const fullPath = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      scanDir(fullPath);
    } else if (/\.(css|js|blade\.php|php)$/i.test(entry.name)) {
      totalScanned++;
      if (allowedFiles.includes(path.normalize(fullPath))) {
        continue;
      }
      const content = fs.readFileSync(fullPath, 'utf8');
      const lines = content.split('\n');
      lines.forEach((line, idx) => {
        // Ignore HTML numeric entities &#...; and URL anchors #... or SVG viewBox/ID references
        const stripped = line.replace(/&#\d+;/g, '').replace(/href="#[^"]*"/g, '').replace(/id="[^"]*"/g, '');
        const matches = stripped.match(colorRegex);
        if (matches) {
          // Exclude CSS variables or token comments or Color::zinc
          const realMatches = matches.filter(m => !m.startsWith('--') && !line.includes('tokens.primitives'));
          if (realMatches.length > 0) {
            findings.push({
              file: fullPath,
              line: idx + 1,
              content: line.trim(),
              matches: realMatches
            });
          }
        }
      });
    }
  }
}

for (const d of targetDirs) {
  scanDir(d);
}

console.log(`Scan completed. Scanned ${totalScanned} application files.`);
console.log(`Total findings: ${findings.length}`);
if (findings.length > 0) {
  findings.forEach(f => {
    console.log(`${f.file}:${f.line} -> [${f.matches.join(', ')}] : ${f.content}`);
  });
} else {
  console.log('SUCCESS: ZERO raw color literals found in application-owned frontend sources!');
}
