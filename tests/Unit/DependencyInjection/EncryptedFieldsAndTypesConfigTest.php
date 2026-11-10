<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Configuration as DoctrineBundleConfiguration;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use Nowo\DoctrineEncryptBundle\DependencyInjection\Configuration;
use Nowo\DoctrineEncryptBundle\DependencyInjection\DoctrineEncryptExtension;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedJsonType;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedStringType;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedTextType;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedTypeRuntime;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedTypeRuntimeConfigurator;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Mapping\EncryptedFieldMap;
use Nowo\DoctrineEncryptBundle\NowoDoctrineEncryptBundle;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\PlainAddress;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\PlainProfile;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\PlainProfileChild;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\Util\XmlUtils;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;

/**
 * encrypted_fields, property_access and dbal_types configuration.
 */
final class EncryptedFieldsAndTypesConfigTest extends TestCase
{
    public function testDefaultsKeepTodaysBehaviour(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[]]);

        self::assertSame([], $config['encrypted_fields']);
        self::assertSame('auto', $config['property_access']);
        self::assertFalse($config['dbal_types']);

        $container  = $this->load([]);
        $subscriber = $container->getDefinition('nowo_doctrine_encrypt.orm_subscriber');
        self::assertSame('auto', $subscriber->getArgument(4));
        self::assertEquals(new Reference('nowo_doctrine_encrypt.encrypted_field_map'), $subscriber->getArgument(3));
        self::assertSame([], $container->getDefinition('nowo_doctrine_encrypt.encrypted_field_map')->getArgument(0));
        self::assertFalse($container->hasDefinition(DoctrineEncryptExtension::DBAL_TYPE_RUNTIME_SERVICE));
        self::assertSame([], $container->getParameter('nowo_doctrine_encrypt.dbal_types'));
    }

    public function testYamlShape(): void
    {
        $container = $this->load([
            'profiles'         => ['personal_data' => [], 'financial_data' => []],
            'property_access'  => 'reflection',
            'encrypted_fields' => [
                PlainProfile::class => ['properties' => ['taxId' => null, 'nickname' => 'financial_data'], 'embedded' => 'address'],
                PlainAddress::class => ['properties' => ['street' => 'default']],
            ],
        ]);

        self::assertSame('reflection', $container->getDefinition('nowo_doctrine_encrypt.orm_subscriber')->getArgument(4));
        $fields = $container->getDefinition('nowo_doctrine_encrypt.encrypted_field_map')->getArgument(0);
        self::assertSame([
            PlainProfile::class => ['properties' => ['taxId' => null, 'nickname' => 'financial_data'], 'embedded' => ['address']],
            PlainAddress::class => ['properties' => ['street' => 'default'], 'embedded' => []],
        ], $fields);
        self::assertSame(['taxId' => 'default', 'nickname' => 'financial_data'], (new EncryptedFieldMap($fields))->getProperties(PlainProfileChild::class));
    }

    public function testXmlShape(): void
    {
        // What XmlUtils produces for:
        // <encrypted-field class="...PlainProfile">
        //   <property name="taxId"/><property name="nickname">financial_data</property>
        //   <property name="street" profile="financial_data"/><embedded>address</embedded>
        // </encrypted-field>
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'encrypted_field' => [[
                'class'    => PlainProfile::class,
                'property' => [
                    ['name' => 'taxId'],
                    ['name' => 'nickname', 'value' => 'financial_data'],
                    ['name' => 'street', 'profile' => 'financial_data'],
                ],
                'embedded' => 'address',
            ]],
        ]]);

        self::assertEquals([
            PlainProfile::class => ['properties' => ['taxId' => null, 'nickname' => 'financial_data', 'street' => 'financial_data'], 'embedded' => ['address']],
        ], $config['encrypted_fields']);
    }

    public function testXmlDocumentAsTheXmlLoaderReadsIt(): void
    {
        $xml = <<<'XML'
            <container xmlns:nowo-doctrine-encrypt="http://example.org/schema/dic/nowo_doctrine_encrypt">
                <nowo-doctrine-encrypt:config property-access="reflection" dbal-types="true">
                    <nowo-doctrine-encrypt:encrypted-field class="Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\PlainProfile">
                        <nowo-doctrine-encrypt:property name="taxId"/>
                        <nowo-doctrine-encrypt:property name="nickname">financial_data</nowo-doctrine-encrypt:property>
                        <nowo-doctrine-encrypt:embedded>address</nowo-doctrine-encrypt:embedded>
                    </nowo-doctrine-encrypt:encrypted-field>
                    <nowo-doctrine-encrypt:encrypted-field class="Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\PlainAddress">
                        <nowo-doctrine-encrypt:property name="street" profile="financial_data"/>
                    </nowo-doctrine-encrypt:encrypted-field>
                </nowo-doctrine-encrypt:config>
            </container>
            XML;
        $document = new DOMDocument();
        $document->loadXML($xml);
        $element = $document->getElementsByTagNameNS('http://example.org/schema/dic/nowo_doctrine_encrypt', 'config')->item(0);
        self::assertInstanceOf(DOMElement::class, $element);

        $config = (new Processor())->processConfiguration(new Configuration(), [XmlUtils::convertDomElementToArray($element)]);

        self::assertSame('reflection', $config['property_access']);
        self::assertTrue($config['dbal_types']);
        self::assertEquals([
            PlainProfile::class => ['properties' => ['taxId' => null, 'nickname' => 'financial_data'], 'embedded' => ['address']],
            PlainAddress::class => ['properties' => ['street' => 'financial_data'], 'embedded' => []],
        ], $config['encrypted_fields']);
    }

    /**
     * @param array<string, mixed> $fields
     */
    #[DataProvider('invalidFields')]
    public function testInvalidEncryptedFieldsFailTheBuild(array $fields, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $this->load(['profiles' => ['personal_data' => []], 'encrypted_fields' => $fields]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidFields(): iterable
    {
        yield 'unknown class' => [['App\\Missing' => ['properties' => ['x' => null]]], 'class "App\\Missing" does not exist'];
        yield 'unknown property' => [[PlainProfile::class => ['properties' => ['missing' => null]]], 'has no property "missing"'];
        yield 'unknown profile' => [[PlainProfile::class => ['properties' => ['taxId' => 'nope']]], 'uses unknown profile "nope"'];
        yield 'also #[Encrypted]' => [[User::class => ['properties' => ['name' => null]]], 'already carries #[Encrypted]'];
        yield 'property and embedded' => [[PlainProfile::class => ['properties' => ['address' => null], 'embedded' => ['address']]], 'cannot be both'];
        yield 'unknown embedded' => [[PlainProfile::class => ['embedded' => ['missing']]], 'has no property "missing"'];
    }

    public function testInheritedPrivatePropertyIsAccepted(): void
    {
        $container = $this->load(['encrypted_fields' => [PlainProfileChild::class => ['properties' => ['taxId' => null]]]]);

        self::assertArrayHasKey(PlainProfileChild::class, $container->getDefinition('nowo_doctrine_encrypt.encrypted_field_map')->getArgument(0));
    }

    public function testDbalTypesRegisterTheRuntimeConfigurator(): void
    {
        $container = $this->load(['profiles' => ['personal_data' => [], 'financial_data' => []], 'on_decrypt_failure' => 'throw', 'dbal_types' => true]);

        $definition = $container->getDefinition(DoctrineEncryptExtension::DBAL_TYPE_RUNTIME_SERVICE);
        self::assertSame(EncryptedTypeRuntimeConfigurator::class, $definition->getClass());
        self::assertTrue($definition->isPublic());
        self::assertSame('throw', $definition->getArgument(1));
        $types = $container->getParameter('nowo_doctrine_encrypt.dbal_types');
        self::assertIsArray($types);
        self::assertContains('encrypted_string.financial_data', $types);
        self::assertContains('encrypted_json', $types);
    }

    public function testDbalTypeMap(): void
    {
        self::assertSame([
            'encrypted_string'               => EncryptedStringType::class,
            'encrypted_text'                 => EncryptedTextType::class,
            'encrypted_json'                 => EncryptedJsonType::class,
            'encrypted_string.personal_data' => EncryptedStringType::class,
            'encrypted_text.personal_data'   => EncryptedTextType::class,
            'encrypted_json.personal_data'   => EncryptedJsonType::class,
        ], DoctrineEncryptExtension::dbalTypeMap(['default', 'personal_data', 'personal_data', '']));
    }

    public function testPrependRegistersTypesInDoctrineOnlyWhenEnabled(): void
    {
        $container = $this->containerWithDoctrine();
        $container->prependExtensionConfig('nowo_doctrine_encrypt', ['profiles' => ['personal_data' => []]]);
        (new DoctrineEncryptExtension())->prepend($container);
        self::assertSame([], $container->getExtensionConfig('doctrine'));

        $container = $this->containerWithDoctrine();
        $container->loadFromExtension('nowo_doctrine_encrypt', ['profiles' => ['personal_data' => []]]);
        $container->loadFromExtension('nowo_doctrine_encrypt', ['dbal_types' => true, 'configs' => [['name' => 'legacy_xml']], 'profile' => 'ignored']);
        (new DoctrineEncryptExtension())->prepend($container);

        $types = $container->getExtensionConfig('doctrine')[0]['dbal']['types'];
        self::assertSame(['class' => EncryptedStringType::class], $types['encrypted_string']);
        self::assertSame(['class' => EncryptedJsonType::class], $types['encrypted_json.personal_data']);
        self::assertSame(['class' => EncryptedTextType::class], $types['encrypted_text.legacy_xml']);

        // DoctrineBundle accepts the prepended shape; a host entry with the same name wins.
        $doctrineConfigs   = $container->getExtensionConfig('doctrine');
        $doctrineConfigs[] = ['dbal' => ['url' => 'sqlite:///:memory:', 'types' => ['encrypted_text' => 'App\\HostType']]];
        $processed         = (new Processor())->processConfiguration(new DoctrineBundleConfiguration(false), $doctrineConfigs);
        self::assertSame(EncryptedStringType::class, $processed['dbal']['types']['encrypted_string']['class']);
        self::assertSame('App\\HostType', $processed['dbal']['types']['encrypted_text']['class']);
    }

    public function testPrependWithoutDoctrineDoesNothing(): void
    {
        $container = $this->container();
        $container->registerExtension(new DoctrineEncryptExtension());
        $container->loadFromExtension('nowo_doctrine_encrypt', ['dbal_types' => true]);
        (new DoctrineEncryptExtension())->prepend($container);

        self::assertFalse($container->hasExtension('doctrine'));
    }

    public function testBundleBootConfiguresAndShutdownClearsTheTypes(): void
    {
        EncryptedTypeRuntime::clear();
        $registry  = new EncryptorRegistry([]);
        $container = new Container();
        $container->set(DoctrineEncryptExtension::DBAL_TYPE_RUNTIME_SERVICE, new EncryptedTypeRuntimeConfigurator($registry, DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_NULL));

        $bundle = new NowoDoctrineEncryptBundle();
        $bundle->setContainer($container);
        $bundle->boot();
        self::assertTrue(EncryptedTypeRuntime::isConfigured());
        self::assertSame('null', EncryptedTypeRuntime::onDecryptFailure());

        $bundle->shutdown();
        self::assertFalse(EncryptedTypeRuntime::isConfigured());
    }

    public function testBundleBootWithoutDbalTypesDoesNothing(): void
    {
        EncryptedTypeRuntime::clear();
        $bundle = new NowoDoctrineEncryptBundle();
        $bundle->boot();
        $bundle->setContainer(new Container());
        $bundle->boot();
        $bundle->shutdown();

        self::assertFalse(EncryptedTypeRuntime::isConfigured());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = $this->container();
        (new DoctrineEncryptExtension())->load([$config], $container);

        return $container;
    }

    private function container(): ContainerBuilder
    {
        return new ContainerBuilder(new ParameterBag(['kernel.debug' => false, 'kernel.environment' => 'dev']));
    }

    private function containerWithDoctrine(): ContainerBuilder
    {
        $container = $this->container();
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'doctrine';
            }
        });
        $container->registerExtension(new DoctrineEncryptExtension());

        return $container;
    }
}
