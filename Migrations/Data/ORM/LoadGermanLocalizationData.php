<?php

namespace Oro\Bundle\GermanLocalizationBundle\Migrations\Data\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\Intl\Intl;

use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\CurrencyBundle\Migrations\Data\ORM\SetDefaultCurrencyFromLocale;
use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\LocaleBundle\DependencyInjection\Configuration;
use Oro\Bundle\LocaleBundle\Migrations\Data\ORM\LoadLocalizationData;
use Oro\Bundle\TranslationBundle\Entity\Language;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Migrations\Data\ORM\LoadRolesData;

class LoadGermanLocalizationData extends AbstractFixture implements ContainerAwareInterface, DependentFixtureInterface
{
    use ContainerAwareTrait;

    const LOCALE = 'de_DE';

    /**
     * {@inheritdoc}
     */
    public function load(ObjectManager $manager)
    {
        $language = $manager->getRepository(Language::class)->findOneBy(['code' => self::LOCALE,]);
        if (!$language) {
            $language = $this->createLanguage(self::LOCALE, $manager);
            $manager->persist($language);
            $manager->flush($language);
        }

        $localization = $this->getLocalization($manager, self::LOCALE, $language);
        if (!$localization) {
            $localization = $this->createLocalization($manager, self::LOCALE, $language->getCode());
            $manager->persist($localization);
            $manager->flush($localization);
        }

        /* @var $configManager ConfigManager */
        $configManager = $this->container->get('oro_config.global');

        //Add German language to the list of enabled
        $enabledLanguages = (array)$configManager->get(Configuration::getConfigKeyByName('languages'));
        $configManager->set(
            Configuration::getConfigKeyByName('languages'),
            array_unique(array_merge($enabledLanguages, [self::LOCALE]))
        );

        //Set Locale
        $configManager->set('oro_locale.locale', self::LOCALE);

        //Add German localization to the list of enabled
        $enabledLocalizations = $configManager->get(
            Configuration::getConfigKeyByName(Configuration::ENABLED_LOCALIZATIONS)
        );
        $configManager->set(
            Configuration::getConfigKeyByName(Configuration::ENABLED_LOCALIZATIONS),
            array_unique(array_merge($enabledLocalizations, [$localization->getId()]))
        );

        $configManager->flush();
    }

    /**
     * @param ObjectManager $manager
     * @param string $locale
     * @param Language $language
     *
     * @return Localization
     */
    protected function getLocalization(ObjectManager $manager, $locale, Language $language)
    {
        return $manager->getRepository('OroLocaleBundle:Localization')
            ->findOneBy(['language' => $language, 'formattingCode' => $locale,]);
    }

    /**
     * @param ObjectManager $manager
     * @param string $locale
     * @param string $languageCode
     *
     * @return null|Localization
     */
    protected function createLocalization(ObjectManager $manager, $locale, $languageCode)
    {
        $language = $manager->getRepository(Language::class)->findOneBy([
            'code' => $languageCode,
            'enabled' => true,
        ]);
        if (!$language) {
            return null;
        }

        $localization = new Localization();
        $title = Intl::getLocaleBundle()->getLocaleName($locale, $locale);
        $localization->setLanguage($language)
            ->setFormattingCode($locale)
            ->setName($title)
            ->setDefaultTitle($title);
        $manager->persist($localization);
        $manager->flush($localization);

        return $localization;
    }

    /**
     * @param $code
     * @param $manager
     *
     * @return Language
     */
    protected function createLanguage($code, $manager)
    {
        $user = $this->getUser($manager);
        $language = new Language();
        $language->setCode($code)
            ->setEnabled(true)
            ->setOrganization($user->getOrganization())
            ->setOwner($user);

        return $language;
    }

    /**
     * @param ObjectManager $manager
     *
     * @throws \RuntimeException
     *
     * @return User
     */
    protected function getUser(ObjectManager $manager)
    {
        $role = $manager->getRepository('OroUserBundle:Role')->findOneBy(['role' => LoadRolesData::ROLE_ADMINISTRATOR]);
        if (!$role) {
            throw new \RuntimeException(sprintf('%s role should exist.', LoadRolesData::ROLE_ADMINISTRATOR));
        }

        $user = $manager->getRepository('OroUserBundle:Role')->getFirstMatchedUser($role);
        if (!$user) {
            throw new \RuntimeException(
                sprintf('At least one user with role %s should exist.', LoadRolesData::ROLE_ADMINISTRATOR)
            );
        }

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function getDependencies()
    {
        return [
            LoadLocalizationData::class,
            SetDefaultCurrencyFromLocale::class
        ];
    }
}
