import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const readSource = (path: string): string =>
    readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

test('public mobile shell keeps the hero and locale selector compact', () => {
    const home = readSource('resources/js/pages/Home.vue');
    const localeSwitcher = readSource(
        'resources/js/components/LocaleSwitcher.vue',
    );

    assert.match(home, /mt-4 min-h-44/);
    assert.match(home, /text-\[1\.75rem\]/);
    assert.match(localeSwitcher, /sr-only sm:not-sr-only/);
    assert.match(localeSwitcher, /sm:rounded-full/);
});

test('public mobile navigation owns the account action', () => {
    const header = readSource('resources/js/components/PublicHeader.vue');

    assert.match(header, /data-test="mobile-account-section"/);
    assert.match(header, /md:flex/);
    assert.match(header, /redirect: page\.url/);
});

test('portal nearby results stay inside the iOS viewport', () => {
    const portal = readSource('resources/js/pages/Portal/Index.vue');
    const styles = readSource('resources/css/app.css');

    assert.match(portal, /data-test="nearby-communities-container"/);
    assert.match(portal, /mt-6 grid min-w-0 gap-4/);
    assert.match(portal, /min-w-0 flex-1 truncate/);
    assert.doesNotMatch(
        portal,
        /(?:<\/div>\s*){3}<div\s+v-if="nearbyCommunities\.length"/,
    );
    assert.match(styles, /overscroll-behavior-x: none/);
    assert.match(styles, /overflow-x: clip/);
    assert.match(styles, /#app \{/);
});

test('shared form controls cannot expand the mobile viewport', () => {
    const styles = readSource('resources/css/app.css');
    const input = readSource('resources/js/components/ui/input/Input.vue');
    const select = readSource(
        'resources/js/components/ui/select/SelectTrigger.vue',
    );
    const phone = readSource('resources/js/components/PhoneInput.vue');
    const money = readSource('resources/js/components/MoneyInput.vue');
    const password = readSource('resources/js/components/PasswordInput.vue');
    const scrollDialog = readSource(
        'resources/js/components/ui/dialog/DialogScrollContent.vue',
    );

    assert.match(styles, /:where\(input, select, textarea\)/);
    assert.match(styles, /max-width: 100%/);
    assert.match(input, /w-full min-w-0 max-w-full/);
    assert.match(select, /w-full min-w-0 max-w-full/);
    assert.match(phone, /w-full min-w-0 max-w-full/);
    assert.match(money, /w-full min-w-0 max-w-full/);
    assert.match(password, /w-full min-w-0 max-w-full/);
    assert.match(scrollDialog, /max-w-\[calc\(100%-2rem\)\]/);
});


test('installed PWA uses a contained scroll shell and native bottom navigation', () => {
    const app = readSource('resources/js/app.ts');
    const navigation = readSource(
        'resources/js/components/PwaBottomNavigation.vue',
    );
    const styles = readSource('resources/css/app.css');

    assert.match(app, /h\(PwaBottomNavigation\)/);
    assert.match(navigation, /display-mode: standalone/);
    assert.match(navigation, /StandaloneNavigator\)\.standalone/);
    assert.match(navigation, /library\.bible|bible as bibleRoute/);
    assert.match(navigation, /nav\.news/);
    assert.match(navigation, /nav\.events/);
    assert.match(navigation, /nav\.profile/);
    assert.match(navigation, /page\.component !== 'Library\/Bible'/);
    assert.match(navigation, /currentPath\.value\.startsWith\('\/dashboard'\)/);
    assert.match(navigation, /currentPath\.value\.startsWith\('\/classrooms'\)/);
    assert.match(app, /'Classrooms\/Index'/);
    assert.match(app, /'Classrooms\/Portal'/);
    assert.match(navigation, /safe-area-inset-bottom/);
    assert.match(styles, /html\.pwa-public-shell body/);
    assert.match(styles, /position: fixed/);
    assert.match(styles, /overflow-y: auto/);
    assert.match(styles, /--pwa-bottom-navigation-height/);
    assert.match(styles, /\.public-top-navigation/);
    assert.match(styles, /scrollbar-width: none/);
    assert.match(styles, /::-webkit-scrollbar/);
});


test('every shared public header participates in the PWA sticky shell', () => {
    const publicHeader = readSource(
        'resources/js/components/PublicHeader.vue',
    );
    const portalHeader = readSource(
        'resources/js/components/PortalHeader.vue',
    );
    const welcome = readSource('resources/js/pages/Welcome.vue');

    assert.match(publicHeader, /public-top-navigation/);
    assert.match(portalHeader, /public-top-navigation/);
    assert.match(welcome, /public-top-navigation/);
});


test('authenticated mobile workspaces contain controls and reserve PWA navigation', () => {
    const workspace = readSource('resources/js/pages/settings/Workspace.vue');
    const dashboard = readSource('resources/js/pages/Dashboard.vue');
    const layout = readSource(
        'resources/js/layouts/app/AppSidebarLayout.vue',
    );
    const phone = readSource('resources/js/components/PhoneInput.vue');
    const navigation = readSource(
        'resources/js/components/PwaBottomNavigation.vue',
    );
    const styles = readSource('resources/css/app.css');

    assert.match(workspace, /overflow-x-clip/);
    assert.match(workspace, /max-w-3xl overflow-hidden/);
    assert.match(workspace, /type="file"[\s\S]*max-w-full overflow-hidden/);
    assert.match(phone, /w-0 min-w-0 flex-1/);
    assert.match(dashboard, /min-w-0 max-w-full/);
    assert.match(layout, /dashboard-theme min-w-0 max-w-full/);
    assert.match(navigation, /settings\/Workspace/);
    assert.match(styles, /pwa-navigation-visible \.dashboard-theme/);
});

test('portal navigation and global dashboard use platform context', () => {
    const portalHeader = readSource(
        'resources/js/components/PortalHeader.vue',
    );
    const sidebar = readSource('resources/js/components/AppSidebar.vue');
    const dashboard = readSource('resources/js/pages/Dashboard.vue');
    const passkey = readSource(
        'resources/js/components/PasskeyVerify.vue',
    );

    assert.match(portalHeader, /isGlobalAdministrator/);
    assert.match(portalHeader, /portal\.open_dashboard/);
    assert.doesNotMatch(portalHeader, /privacy\(\)/);
    assert.match(sidebar, /isPlatformDashboard/);
    assert.match(sidebar, /adminMultiCongregationIndex/);
    assert.match(sidebar, /\/dashboard\/live-streams/);
    assert.match(dashboard, /context: 'church' \| 'platform'/);
    assert.match(dashboard, /dashboard\.platform\.title/);
    assert.match(passkey, /localizedPasskeyError/);
    assert.doesNotMatch(passkey, /:message="error"/);
});

test('dashboard submenus stay compact and registration fields use shared controls', () => {
    const navigation = readSource('resources/js/components/NavMain.vue');
    const registrations = readSource(
        'resources/js/pages/Admin/EventRegistrations.vue',
    );

    assert.match(navigation, /CollapsibleContent/);
    assert.match(navigation, /:default-open="isCurrentUrl\(item\.href\)"/);
    assert.match(navigation, /data-\[state=open\]:rotate-90/);
    assert.match(registrations, /import \{ Input \}/);
    assert.match(registrations, /isMultilineField/);
    assert.match(registrations, /read-only:bg-muted\/50/);
    assert.match(registrations, /border-input bg-background/);
});
