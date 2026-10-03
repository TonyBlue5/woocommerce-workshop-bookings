const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const fixture=require('../checkout-fixture.json');
(async()=>{
 const browser=await chromium.launch({headless:true});
 try {
  for(const email of ['browser-a@example.org','browser-b@example.org','browser-a@example.org']) {
   const context=await browser.newContext({locale:'en-US'}),page=await context.newPage();
   try {
   await page.goto(fixture.referral);
   const cookie=(await context.cookies()).find(c=>c.name==='kwb_ref');
   assert.ok(cookie&&cookie.httpOnly,'referral click must set HttpOnly signed cookie');
   await page.goto(fixture.product_url);
   await page.locator('.kwb-select-month').click();
   assert.ok(await page.locator('#kwb_months').inputValue(),'calendar did not select month');
   await Promise.all([page.waitForNavigation(),page.locator('button.single_add_to_cart_button').click()]);
   await page.goto(fixture.checkout);
   await page.locator('#billing_first_name').fill('Browser');await page.locator('#billing_last_name').fill('Guest');
   await page.locator('#billing_country').selectOption('GB');
   await page.locator('#billing_address_1').fill('10 Test Street');await page.locator('#billing_city').fill('London');
   await page.locator('#billing_postcode').fill('SW1A 1AA');await page.locator('#billing_phone').fill('02079460000');await page.locator('#billing_email').fill(email);
   const payment=page.locator('#payment_method_bacs');if(await payment.isVisible())await payment.check();
   await page.locator('#place_order').click();
   await page.waitForURL(url=>url.href.includes('order-received'),{timeout:60000});
   const rendered=await page.locator('body').innerText();assert.ok(!rendered.includes('_kwb_'),'thank-you page leaks internal metadata');
   } catch(error) {
    console.error('Checkout URL:',page.url(),'Page:',(await page.locator('body').innerText()).slice(0,6000));
    await page.screenshot({path:'checkout-failure.png',fullPage:true});throw error;
   }
   await context.close();
  }
  console.log('browser-checkout-created');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
