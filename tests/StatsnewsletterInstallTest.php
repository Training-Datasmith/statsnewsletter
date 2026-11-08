<?php

class StatsnewsletterInstallTest extends StatsnewsletterTestCase
{
    public function testConstructorPublishesMetadataAndSubscriptionTable()
    {
        $module = new statsnewsletter();
        $this->assertSame('statsnewsletter', $module->name);
        $this->assertSame('administration', $module->tab);
        $this->assertSame('2.1.0', $module->version);
        $this->assertSame('PrestaShop', $module->author);
        $this->assertSame(0, $module->need_instance);
        $this->assertSame('1.7.1.0', $module->ps_versions_compliancy['min']);
        $this->assertSame(_PS_VERSION_, $module->ps_versions_compliancy['max']);
        $this->assertSame('Newsletter', $module->displayName);
        $this->assertContains('newsletter registrations', $module->description);

        $ref = new ReflectionClass($module);
        $table = $ref->getProperty('table_name');
        $table->setAccessible(true);
        $this->assertSame('ps_emailsubscription', $table->getValue($module));

        $mod = $ref->getProperty('newsletter_module_name');
        $mod->setAccessible(true);
        $this->assertSame('ps_emailsubscription', $mod->getValue($module));

        $human = $ref->getProperty('newsletter_module_human_readable_name');
        $human->setAccessible(true);
        $this->assertSame('Email subscription', $human->getValue($module));
    }

    public function testConfigXmlNameAndVersionMatchClass()
    {
        $module = new statsnewsletter();
        $xml = simplexml_load_file(STATSNEWSLETTER_TEST_ROOT . '/config.xml');
        $this->assertSame('statsnewsletter', (string) $xml->name);
        $this->assertSame('2.1.0', (string) $xml->version);
        $this->assertSame($module->version, (string) $xml->version);
    }

    public function testInstallPersistsModuleAndDisplayAdminStatsModulesHook()
    {
        $module = new statsnewsletter();
        $this->assertTrue($module->install());
        $row = Db::getInstance()->getRow(
            "SELECT `id_module` FROM `ps_module` WHERE `name` = 'statsnewsletter'"
        );
        $this->assertNotFalse($row);
        $hook = Db::getInstance()->getRow(
            "SELECT `id_hook` FROM `ps_hook` WHERE `name` = 'displayAdminStatsModules'"
        );
        $this->assertNotFalse($hook);
        $this->assertTrue(TestEnvironment::hookIsRegistered('displayAdminStatsModules'));
    }

    public function testInstallSkipsHookWhenParentInstallFails()
    {
        Db::getInstance()->insert('ps_module', array(
            'name' => 'statsnewsletter',
            'active' => 1,
        ));
        $module = new statsnewsletter();
        $this->assertFalse($module->install());
        $this->assertFalse(TestEnvironment::hookIsRegistered('displayAdminStatsModules'));
    }
}
