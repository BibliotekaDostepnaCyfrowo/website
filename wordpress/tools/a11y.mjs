// Automated accessibility check (axe-core, WCAG 2.2 A/AA rules) of the given
// pages at desktop and mobile widths. Automated rules catch roughly a third of
// WCAG problems; keyboard, screen reader and content checks still need a person.
//
// Usage: node a11y.mjs name=url [name=url ...]
import { chromium } from 'playwright';
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';

const axeSource = readFileSync(createRequire(import.meta.url).resolve('axe-core/axe.min.js'), 'utf8');
const tags = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
const viewports = { desktop: { width: 1440, height: 900 }, mobile: { width: 320, height: 640 } };

const browser = await chromium.launch({
  args: ['--host-resolver-rules=MAP localhost host.docker.internal'],
});
let total = 0;
for (const target of process.argv.slice(2)) {
  const [name, url] = target.split(/=(.*)/s);
  for (const [vpName, viewport] of Object.entries(viewports)) {
    const page = await browser.newPage({ viewport, locale: 'pl-PL' });
    await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 });
    await page.addScriptTag({ content: axeSource });
    const result = await page.evaluate(
      (tags) =>
        window.axe.run(document, {
          runOnly: { type: 'tag', values: tags },
          // Third-party map iframes are outside our control.
          exclude: [['.bdc-map iframe']],
        }),
      tags,
    );
    // Content wider than the viewport at 320px breaks reflow (WCAG 1.4.10).
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    console.log(`\n${name} (${vpName}): ${result.violations.length} rule(s) violated` + (overflow > 0 ? `, page ${overflow}px wider than screen` : ''));
    for (const v of result.violations) {
      total += v.nodes.length;
      console.log(`  [${v.impact}] ${v.id}: ${v.help} (${v.nodes.length}×)`);
      for (const node of v.nodes.slice(0, 3)) {
        console.log(`      ${node.target.join(' ')} :: ${node.failureSummary.split('\n').slice(1, 2).join(' ').trim().slice(0, 160)}`);
      }
    }
    await page.close();
  }
}
await browser.close();
console.log(`\nTotal failing elements: ${total}`);
