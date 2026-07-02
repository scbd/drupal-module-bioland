1.  the form is not showing admin tab any more

2.  /en/admin/config/bioland/settings/front-end/home-page text needs to be different for bsl sites vs bl2.  if bsl we show another prop from fonfig.  we need to add that

new BSL body text:
"About Home Page Heroe

Heros are the large banner images displayed at the top of the home page. Since the home page layout cannot be directly edited, this is where you edit the hero banner for the home page.
"

Make this variant, make the translated versions in the .po files.  Load them on install based on sites locales.

ensure it happens on install or update.

3. https://seed.bsl.staging.cbd.int/en/admin/config/bioland/settings every one of these fields do not save the values.  They are mirriors of the system version https://seed.bsl.staging.cbd.int/en/admin/config/system/site-information for site name and slogan. Timzone should mirrior the one here https://seed.bsl.staging.cbd.int/en/admin/config/regional/settings.

the intent if site name or a translation site slogan or translation changed in our form it updates the system and reflects in /admin/config/system/site-information and vice versa.

same with time zone changed in are form auto changed in /admin/config/regional/settings and vice versa.

prenently a change in either location does not save!


- some translated screen titles/menus are not translated in the es version as an example meaning not translated for any locale.