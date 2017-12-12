<?php

namespace VM5\EntityTranslationsBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use VM5\EntityTranslationsBundle\Tests\Entity\Language;
use VM5\EntityTranslationsBundle\Tests\Entity\News;
use VM5\EntityTranslationsBundle\Tests\Entity\NewsTranslation;
use VM5\EntityTranslationsBundle\Translator;

class TranslatorTest extends WebTestCase
{
    private function buildDb($kernel)
    {
        $application = new Application($kernel);
        $application->setAutoExit(false);

        $application->run(
            new ArrayInput(
                array(
                    'doctrine:schema:create',
                )
            ),
            new ConsoleOutput()
        );
    }

    public function testLoadLocaleFromSymfonyTranslatorDefault()
    {
        $client = static::createClient();

        $kernel = $client->getKernel();

        $container = $kernel->getContainer();

        /** @var Translator $translator */
        $translator = $container->get(Translator::class);

        $this->assertEquals('en', $translator->getLocale());

        $client->request('GET', '/');

        $this->assertEquals('bg', $translator->getLocale());
        $this->assertEquals(['en', 'fi'], $translator->getFallbackLocales());
    }

    public function testLoadLocaleFromSymfonyTranslator()
    {
        $client = static::createClient();

        $kernel = $client->getKernel();

        $container = $kernel->getContainer();

        /** @var Translator $translator */
        $translator = $container->get(Translator::class);

        $this->assertEquals('en', $translator->getLocale());

        $client->request('GET', '/fi');

        $this->assertEquals('fi', $translator->getLocale());
        $this->assertEquals(['en', 'fi'], $translator->getFallbackLocales());
    }

    public function testOrmEntityTranslationLoaded()
    {
        $client = static::createClient();
        $kernel = $client->getKernel();
        $this->buildDb($kernel);

        $container = $kernel->getContainer();

        $em = $container->get('doctrine')->getManager();

        $englishLanguage = new Language('en');
        $em->persist($englishLanguage);


        $bulgarianLanguage = new Language('bg');
        $em->persist($bulgarianLanguage);
        $em->flush();

        $newsTranslationEnglish = new NewsTranslation($englishLanguage, 'This is title in english');
        $newsTranslationBulgarian = new NewsTranslation($bulgarianLanguage, 'Това е заглавие на български');

        $news = new News(
            [
                $newsTranslationEnglish,
                $newsTranslationBulgarian,
            ]
        );

        $em->persist($news);
        $em->flush();
        $em->clear();

        /** @var Translator $translator */
        $translator = $container->get(Translator::class);

        $this->assertEquals('en', $translator->getLocale());

        $crawler = $client->request('GET', sprintf('/bg/news/%s', $news->getId()));

        $response = $client->getResponse();

        $this->assertEquals('Това е заглавие на български', $response->getContent());
    }

    public function testOrmEntityTranslationLoadedAfterPersist()
    {
        $client = static::createClient();
        $kernel = $client->getKernel();
        $this->buildDb($kernel);

        $container = $kernel->getContainer();

        $em = $container->get('doctrine')->getManager();

        $translator = $container->get(Translator::class);
        $translator->setLocale('bg');

        $englishLanguage = new Language('en');
        $em->persist($englishLanguage);

        $bulgarianLanguage = new Language('bg');
        $em->persist($bulgarianLanguage);
        $em->flush();

        $newsTranslationEnglish = new NewsTranslation($englishLanguage, 'This is title in english');
        $newsTranslationBulgarian = new NewsTranslation($bulgarianLanguage, 'Това е заглавие на български');

        $news = new News(
            [
                $newsTranslationEnglish,
                $newsTranslationBulgarian,
            ]
        );

        $em->persist($news);
        $em->flush();

        $this->assertNotNull($news->getCurrentTranslation());
        $this->assertEquals('Това е заглавие на български', $news->getCurrentTranslation()->getTitle());
        $this->assertEquals('bg', $news->getCurrentTranslation()->getLanguage()->getLocale());
    }
}