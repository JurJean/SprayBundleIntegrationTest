<?php

namespace Spray\BundleIntegration;

use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\DataFixtures\ContainerAwareLoader;
use Symfony\Component\Config\Loader\LoaderInterface;

/**
 * ORMIntegrationTestCase
 */
abstract class ORMIntegrationTestCase extends IntegrationTestCase
{
    /**
     * Since @beforeClass does not work...
     */
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        static::registerAnnotations();
    }
    
    /**
     * @return void
     */
    public static function registerAnnotations()
    {
        AnnotationRegistry::registerFile('vendor/doctrine/orm/lib/Doctrine/ORM/Mapping/Driver/DoctrineAnnotations.php');
        AnnotationRegistry::registerFile('vendor/symfony/symfony/src/Symfony/Bridge/Doctrine/Validator/Constraints/UniqueEntity.php');
        AnnotationRegistry::registerAutoloadNamespace('Symfony\Component\Validator\Constraints', 'vendor/symfony/symfony/src');
    }

    /**
     * {@inheritDoc}
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        parent::registerContainerConfiguration($loader);
        $loader->load(__DIR__ . '/Resources/config/doctrine.yml');
    }
    
    /**
     * Return where your fixtures are located
     * 
     * @return array<string>
     */
    public function registerFixturePaths()
    {
        $paths = array();
        foreach ($this->createKernel()->getBundles() as $bundle) {
            $paths[] = $bundle->getPath().'/DataFixtures/ORM';
        }
        return $paths;
    }
    
    /**
     * @return EntityManager
     */
    protected function createEntityManager()
    {
        return $this->createContainer()->get('doctrine.orm.entity_manager');
    }
    
    /**
     * Since @before does not work...
     */
    public function setUp()
    {
        parent::setUp();
        $this->loadSchema();
        $this->loadFixtures();
    }
    
    /**
     * 
     */
    public function loadSchema()
    {
        $entityManager = $this->createEntityManager();
        $tool = new SchemaTool($this->createEntityManager());
        $tool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }
    
    /**
     * 
     */
    public function loadFixtures()
    {
        $loader = new ContainerAwareLoader($this->createContainer());
        foreach ($this->registerFixturePaths() as $path) {
            if (is_dir($path)) {
                $loader->loadFromDirectory($path);
            }
        }
        $fixtures = $loader->getFixtures();
        if (!$fixtures) {
            throw new InvalidArgumentException(sprintf(
                'Could not find any fixtures to load in: %s',
                "\n\n- ".implode("\n- ", $this->registerFixturePaths())
            ));
        }
        $purger = new ORMPurger($this->createEntityManager());
        $purger->setPurgeMode(ORMPurger::PURGE_MODE_TRUNCATE);
        $executor = new ORMExecutor($this->createEntityManager(), $purger);
        $executor->execute($fixtures);
    }
}
