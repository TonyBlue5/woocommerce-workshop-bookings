const {chromium}=require('playwright');
const assert=require('node:assert/strict');
(async()=>{
 const browser=await chromium.launch({headless:true});
 try {
  const page=await browser.newPage({locale:'en-US'}); // Deliberately US browser + Greek WordPress.
  await page.goto('http://127.0.0.1:8099/wp-login.php');
  await page.locator('#user_login').fill('admin');await page.locator('#user_pass').fill('Only-For-CI-2026!');
  await Promise.all([page.waitForURL('**/wp-admin/**'),page.locator('#wp-submit').click()]);
  await page.goto('http://127.0.0.1:8099/wp-admin/admin.php?page=kwb-dashboard&from=2026-10-03&to=2026-10-31');
  const dates=page.locator('.kwb-date-display');await dates.first().waitFor();
  assert.ok((await page.locator('.kwb-hero h1').textContent()).includes('εργαστήριά'));
  assert.equal(await dates.first().inputValue(),'03/10/2026');
  await dates.first().fill('15/10/2026');await dates.first().press('Tab');
  assert.equal(await page.locator('input.kwb-date-iso').first().inputValue(),'2026-10-15');
  await dates.first().fill('31/02/2026');await dates.first().press('Tab');
  assert.equal(await dates.first().evaluate(el=>el.checkValidity()),false);
  await dates.first().fill('03/10/2026');await dates.first().press('Tab');
  await page.locator('.kwb-sort').nth(5).click();assert.equal(await page.locator('th[aria-sort="ascending"]').count(),1);
  await page.goto('http://127.0.0.1:8099/wp-admin/admin.php?page=kwb-dashboard&tab=broadcast');
  await page.locator('.select2-selection').first().click();
  await page.locator('.select2-search__field').last().fill('Regression');
  await page.locator('.select2-results__option').filter({hasText:'Regression workshop'}).waitFor();
  assert.equal(await page.locator('.select2-results__option').filter({hasText:'Regression regular product'}).count(),0);
  await page.locator('.select2-results__option').filter({hasText:'Regression workshop'}).click();
  const product=await page.locator('select[name="product"]').inputValue();assert.ok(Number(product)>0);
  const cancellation=page.locator('.kwb-date-display');
  await cancellation.fill('08/10/2026');await cancellation.press('Tab');
  assert.equal(await page.locator('.kwb-date-iso').inputValue(),'2026-10-08');
  for(const tab of ['customers','referrals']) {
   await page.goto('http://127.0.0.1:8099/wp-admin/admin.php?page=kwb-dashboard&tab='+tab);
   const field=page.locator('select[name="products[]"]');assert.equal(await field.getAttribute('multiple'),'multiple');
   await page.locator('.select2-search__field').last().fill('Regression');
   await page.locator('.select2-results__option').filter({hasText:'Regression workshop'}).click();
   assert.ok((await field.evaluate(el=>Array.from(el.selectedOptions,o=>o.value))).includes(product));
   assert.ok((await page.locator('.select2-selection__choice').allTextContents()).join(' ').includes('Regression workshop'));
   assert.equal(await page.locator('input[type="date"],input[type="month"]').count(),0);
  }
  await page.goto('http://127.0.0.1:8099/wp-admin/admin.php?page=kwb-dashboard&product='+product);
  await page.locator('.select2-selection__clear').click();
  assert.ok(['0',''].includes(await page.locator('select[name="product"]').inputValue()));
  await page.goto('http://127.0.0.1:8099/wp-admin/admin.php?page=kwb-calendar&product='+product+'&month=2026-10');
  assert.equal(await page.locator('.kwb-date-display').inputValue(),'10/2026');
  await page.locator('.kwb-date-display').fill('11/2026');await page.locator('.kwb-date-display').press('Tab');
  assert.equal(await page.locator('.kwb-date-iso').inputValue(),'2026-11');
  await page.goto('http://127.0.0.1:8099/wp-admin/post.php?post='+product+'&action=edit');
  await page.locator('a[href="#kwb_booking_product_data"]').click();
  await page.locator('#kwb-add-blackout').click();
  const blackout=page.locator('#kwb-blackout-rows .kwb-date-display').last();
  await blackout.fill('25/12/2026');await blackout.press('Tab');
  assert.ok((await page.locator('#_kwb_blackouts').inputValue()).includes('2026-12-25'));
  assert.equal(await page.locator('#kwb_booking_product_data input[type="date"]').count(),0);
  await Promise.all([page.waitForURL(url=>url.searchParams.get('message')==='1'),page.locator('#publish').click()]);
  await page.locator('a[href="#kwb_booking_product_data"]').click();
  assert.ok((await page.locator('#_kwb_blackouts').inputValue()).includes('2026-12-25'));
  assert.equal(await page.locator('#kwb-blackout-rows .kwb-date-display').last().inputValue(),'25/12/2026');
  await page.screenshot({path:'ui-controls.png',fullPage:true});
  console.log('ui-controls-ok');
 } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exit(1);});
