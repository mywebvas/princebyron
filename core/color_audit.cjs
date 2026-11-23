const fs = require('fs');
const path = require('path');

// Regex patterns to detect genuine color literals
// 1. Hex codes (#fff, #ffffff, #ffffff80) that are NOT HTML entities (not preceded by &)
// and not markdown headers or numbers like Card #4242
const HEX_REGEX = /(?<!&)#(?:[0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{3,4})\b/g;

// 2. rgb / rgba
const RGB_REGEX = /\brgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+(?:\s*,\s*[\d\.]+)?\s*\)/gi;

// 3. hsl / hsla
const HSL_REGEX = /\bhsla?\(\s*\d+\s*,\s*[\d\.]+%?\s*,\s*[\d\.]+%?(?:\s*,\s*[\d\.]+)?\s*\)/gi;

// Directories and files to scan
const SCAN_TARGETS = [
  'core/resources/views',
  'core/resources/js',
  'core/resources/css',
  'core/app/Filament',
  'core/app/Providers/Filament'
];

// Exempt files: central token definitions
const EXEMPT_FILES = [
  path.normalize('core/config/tokens.php'),
  path.normalize('core/resources/css/tokens.css')
];

function getAllFiles(dirPath, arrayOfFiles = []) {
  if (!fs.existsSync(dirPath)) return arrayOfFiles;
  const files = fs.readdirSync(dirPath);

  files.forEach(file => {
    const fullPath = path.join(dirPath, file);
    if (fs.statSync(fullPath).isDirectory()) {
      arrayOfFiles = getAllFiles(fullPath, arrayOfFiles);
    } else {
      arrayOfFiles.push(fullPath);
    }
  });

  return arrayOfFiles;
}

let allFiles = [];
SCAN_TARGETS.forEach(target => {
  const fullTarget = path.normalize(target);
  if (fs.existsSync(fullTarget)) {
    if (fs.statSync(fullTarget).isDirectory()) {
      allFiles = allFiles.concat(getAllFiles(fullTarget));
    } else {
      allFiles.push(fullTarget);
    }
  }
});

allFiles = allFiles.filter(f => {
  const ext = path.extname(f);
  return ext === '.php' || ext === '.js' || ext === '.css' || f.endsWith('.blade.php');
});

console.log(`Scanning ${allFiles.length} application frontend files...`);

const findings = [];

allFiles.forEach(filePath => {
  const norm = path.normalize(filePath);
  if (EXEMPT_FILES.some(ex => norm.endsWith(ex) || norm === ex)) {
    return;
  }

  const content = fs.readFileSync(filePath, 'utf8');
  const lines = content.split('\n');

  lines.forEach((line, idx) => {
    // Exclude anchor links like href="#..." or Card #4242 or HTML entities &#123;
    let hexMatches = line.match(HEX_REGEX);
    if (hexMatches) {
      hexMatches.forEach(hex => {
        // Exclude if inside an HTML entity &#...;
        if (line.includes(`&${hex};`)) return;
        // Exclude href="#..."
        if (line.includes(`href="${hex}"`) || line.includes(`href='${hex}'`)) return;
        // Exclude "Card #4242"
        if (line.includes(`Card ${hex}`)) return;
        // Exclude ID selectors in CSS / JS like $('#...') or document.getElementById('...') or #main-content
        if (line.trim().startsWith(hex) && (line.includes('{') || line.includes(','))) {
          // Could be a CSS id selector, e.g. #toast { ... }
          // Genuine color hex won't be a selector starting a css rule
          return;
        }

        findings.push({
          file: filePath,
          line: idx + 1,
          match: hex,
          type: 'HEX',
          snippet: line.trim()
        });
      });
    }

    let rgbMatches = line.match(RGB_REGEX);
    if (rgbMatches) {
      rgbMatches.forEach(rgb => {
        findings.push({
          file: filePath,
          line: idx + 1,
          match: rgb,
          type: 'RGB(A)',
          snippet: line.trim()
        });
      });
    }

    let hslMatches = line.match(HSL_REGEX);
    if (hslMatches) {
      hslMatches.forEach(hsl => {
        findings.push({
          file: filePath,
          line: idx + 1,
          match: hsl,
          type: 'HSL(A)',
          snippet: line.trim()
        });
      });
    }
  });
});

console.log(`\nAudit Results: ${findings.length} raw color literal findings.\n`);
if (findings.length > 0) {
  findings.forEach(f => {
    console.log(`[${f.type}] ${f.file}:${f.line} -> ${f.match}`);
    console.log(`    Line: ${f.snippet.substring(0, 100)}`);
  });
} else {
  console.log(`PERFECT: 0 raw color literals detected outside config/tokens.php and resources/css/tokens.css!`);
}
