// Fills an EMPTY site with a few demo characters and takes the README screenshots (docs/screenshots).
//   rm -f public_html/App_Data/boblox.db && rm -rf public_html/App_Data/thumbs public_html/App_Data/uploads
//   php -S 127.0.0.1:8099 -t public_html public_html/router.php &
//   node tools/take-screenshots.cjs http://127.0.0.1:8099 docs/screenshots
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

// Seeded catalog ids (App_Code/Database.php, db_seed)
const ITEM = {
  tophat: 1, cone: 2, redcap: 3, bluecap: 4, party: 5, crown: 6, viking: 7, chef: 8, halo: 9, cowboy: 10,
  beanie: 11, bucket: 12, antlers: 13, smile: 14, frown: 15, chompy: 16, noface: 17,
  logotee: 18, smileytee: 19, collar: 20, hoodie: 21, jeans: 22, slacks: 23,
};

const DEMO = [
  { name: 'builderman2', colors: { Head: 6, Torso: 5, LeftArm: 6, RightArm: 6, LeftLeg: 4, RightLeg: 4 },
    items: ['redcap', 'logotee', 'jeans'], pose: 'walk', blurb: 'I build stuff.' },
  { name: 'NoobMaster', colors: { Head: 6, Torso: 8, LeftArm: 6, RightArm: 6, LeftLeg: 19, RightLeg: 19 },
    items: ['cone'], pose: 'zombie', blurb: 'brains' },
  { name: 'PartyGirl', colors: { Head: 31, Torso: 25, LeftArm: 31, RightArm: 31, LeftLeg: 24, RightLeg: 24 },
    items: ['party', 'smileytee'], pose: 'dance', blurb: 'Every day is a party!!' },
  { name: 'CaptainBlox', colors: { Head: 30, Torso: 4, LeftArm: 30, RightArm: 30, LeftLeg: 4, RightLeg: 4 },
    items: ['tophat', 'hoodie', 'slacks'], pose: 'hero', blurb: 'Up, up and away.' },
  { name: 'marcel', admin: true, colors: { Head: 6, Torso: 5, LeftArm: 6, RightArm: 6, LeftLeg: 4, RightLeg: 19 },
    items: ['viking', 'collar', 'jeans', 'smileytee', 'chompy'], pose: 'cheer',
    blurb: 'Hello everyone. I am the owner of BOBLOX! Make an account, dress up your character and add me as a friend :)',
    discord: 'https://discord.com/users/1211021666193506457' },
];

(async () => {
  const [base, outDir] = process.argv.slice(2);
  fs.mkdirSync(outDir, { recursive: true });
  const browser = await chromium.launch({ args: ['--use-gl=angle', '--use-angle=swiftshader', '--enable-unsafe-swiftshader'] });
  const pages = {};
  // the first account becomes the admin, so sign up marcel first
  const order = [DEMO[4], DEMO[0], DEMO[1], DEMO[2], DEMO[3]];
  for (const u of order) {
    const ctx = await browser.newContext({ viewport: { width: 1000, height: 900 }, deviceScaleFactor: 2 });
    const p = await ctx.newPage();
    p.on('pageerror', e => console.log(u.name, 'pageerror', e.message));
    await p.goto(base + '/Login/NewAccount.aspx');
    await p.fill('#UserName', u.name);
    await p.fill('#Password', 'demo-password');
    await p.fill('#ConfirmPassword', 'demo-password');
    await p.check('input[name=AgreeTerms]');
    await p.click('input[type=submit]');
    await p.waitForURL(/Character/);
    await p.waitForTimeout(1000);
    await p.evaluate(async (u) => {
      const call = (svc, m, args) => new Promise((res, rej) => BOBLOX.Ajax.call(svc, m, args, res, rej));
      const ch = (m, args) => new Promise(res => BOBLOX.Character.call(m, args, res));
      for (const part in u.colors) await ch('SetBodyColor', { part, colorId: u.colors[part] });
      for (const id of u.itemIds) { await call('Catalog', 'TakeItem', { itemId: id }); await ch('WearItem', { itemId: id }); }
      await ch('SetPose', { name: u.pose });
      await call('Users', 'UpdateProfile', { blurb: u.blurb, discord: u.discord || '' });
    }, { ...u, itemIds: u.items.map(k => ITEM[k]) });
    await p.waitForFunction(() => /Saved! Drag/.test(document.getElementById('AvatarStatus').textContent), null, { timeout: 60000 });
    await p.waitForTimeout(500);
    pages[u.name] = p;
  }

  // friendships: everyone asks marcel, marcel accepts
  for (const u of DEMO.filter(d => !d.admin)) {
    await pages[u.name].evaluate(() => new Promise(res => BOBLOX.Ajax.call('Friends', 'RequestFriendship', { userId: 1 }, res)));
  }
  const m = pages.marcel;
  for (let id = 2; id <= 5; id++) {
    await m.evaluate((id) => new Promise(res => BOBLOX.Ajax.call('Friends', 'AcceptFriendship', { userId: id }, res)), id);
  }
  // marcel uploads a T-Shirt
  await m.goto(base + '/My/Create.aspx?type=2');
  await m.setInputFiles('#Image', path.join(__dirname, '../public_html/img/525c48a16d539c17460dddd939ab0276.png'));
  await m.fill('#Name', 'My Old Avatar Tee');
  await m.fill('#Description', 'A T-Shirt with my old profile picture on it.');
  await m.click('input[type=submit]');
  await m.waitForLoadState();

  const shot = async (p, url, file, opts = {}) => {
    await p.goto(base + url);
    await p.waitForLoadState('networkidle');
    if (opts.before) await opts.before(p);
    await p.waitForTimeout(opts.wait || 600);
    await p.screenshot({ path: path.join(outDir, file), type: 'jpeg', quality: 82, fullPage: true, clip: opts.clip });
    console.log('saved', file);
  };
  await shot(m, '/marcel', 'profile.jpg', { clip: { x: 40, y: 0, width: 920, height: 830 } });
  await shot(m, '/My/Character.aspx', 'character.jpg', {
    wait: 2500,
    clip: { x: 40, y: 0, width: 920, height: 1330 },
    before: async (p) => {
      await p.waitForFunction(() => /Saved|Drag/.test(document.getElementById('AvatarStatus').textContent));
      await p.click('#Body_Torso');
      await p.click('#CustomPoseToggle');
      await p.evaluate(() => {
        const set = (id, v) => { const el = document.getElementById(id); el.value = v; el.dispatchEvent(new Event('input')); };
        set('Pose_RightArm_swing', 95); set('Pose_RightArm_raise', 20); set('Pose_LeftArm_raise', 40);
        set('Pose_Head_yaw', -25); set('Pose_LeftLeg_swing', 30); set('Pose_RightLeg_swing', -20);
      });
    },
  });
  const guest = await (await browser.newContext({ viewport: { width: 1000, height: 900 }, deviceScaleFactor: 2 })).newPage();
  await shot(guest, '/', 'home.jpg', { clip: { x: 40, y: 0, width: 920, height: 700 } });
  await shot(guest, '/Catalog.aspx?c=Hats', 'catalog.jpg', { clip: { x: 40, y: 0, width: 920, height: 760 } });
  await shot(guest, '/Browse.aspx', 'people.jpg', { clip: { x: 40, y: 0, width: 920, height: 520 } });
  await shot(m, '/My/Create.aspx?type=11', 'create.jpg', { clip: { x: 40, y: 0, width: 920, height: 700 } });
  await browser.close();
})();
