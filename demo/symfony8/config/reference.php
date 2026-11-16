<?php

declare(strict_types=1);

// This file is auto-generated and is for apps only. Bundles SHOULD NOT rely on its content.

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\Config\Loader\ParamConfigurator as Param;

/**
 * This class provides array-shapes for configuring the services and bundles of an application.
 *
 * Services declared with the config() method below are autowired and autoconfigured by default.
 *
 * This is for apps only. Bundles SHOULD NOT use it.
 *
 * Example:
 *
 *     ```php
 *     // config/services.php
 *     namespace Symfony\Component\DependencyInjection\Loader\Configurator;
 *
 *     return App::config([
 *         'services' => [
 *             'App\\' => [
 *                 'resource' => '../src/',
 *             ],
 *         ],
 *     ]);
 *     ```
 *
 * @psalm-type ImportsConfig = list<string|array{
 *     resource: string,
 *     type?: string|null,
 *     ignore_errors?: bool|'not_found',
 * }>
 * @psalm-type ParametersConfig = array<string, scalar|\UnitEnum|array<scalar|\UnitEnum|array<mixed>|Param|null>|Param|null>
 * @psalm-type ArgumentsType = list<mixed>|array<string, mixed>
 * @psalm-type CallType = array<string, ArgumentsType>|array{0:string, 1?:ArgumentsType, 2?:bool}|array{method:string, arguments?:ArgumentsType, returns_clone?:bool}
 * @psalm-type TagsType = list<string|array<string, array<string, mixed>>> // arrays inside the list must have only one element, with the tag name as the key
 * @psalm-type CallbackType = string|array{0:string|ReferenceConfigurator,1:string}|\Closure|ReferenceConfigurator
 * @psalm-type DeprecationType = array{package: string, version: string, message?: string}
 * @psalm-type DefaultsType = array{
 *     public?: bool,
 *     tags?: TagsType,
 *     resource_tags?: TagsType,
 *     autowire?: bool,
 *     autoconfigure?: bool,
 *     bind?: array<string, mixed>,
 * }
 * @psalm-type InstanceofType = array{
 *     shared?: bool,
 *     lazy?: bool|string,
 *     public?: bool,
 *     properties?: array<string, mixed>,
 *     configurator?: CallbackType,
 *     calls?: list<CallType>,
 *     tags?: TagsType,
 *     resource_tags?: TagsType,
 *     autowire?: bool,
 *     bind?: array<string, mixed>,
 *     constructor?: string,
 * }
 * @psalm-type DefinitionType = array{
 *     class?: string,
 *     file?: string,
 *     parent?: string,
 *     shared?: bool,
 *     synthetic?: bool,
 *     lazy?: bool|string,
 *     public?: bool,
 *     abstract?: bool,
 *     deprecated?: DeprecationType,
 *     factory?: CallbackType,
 *     configurator?: CallbackType,
 *     arguments?: ArgumentsType,
 *     properties?: array<string, mixed>,
 *     calls?: list<CallType>,
 *     tags?: TagsType,
 *     resource_tags?: TagsType,
 *     decorates?: string,
 *     decorates_tag?: string,
 *     decoration_inner_name?: string,
 *     decoration_priority?: int,
 *     decoration_on_invalid?: 'exception'|'ignore'|null,
 *     autowire?: bool,
 *     autoconfigure?: bool,
 *     bind?: array<string, mixed>,
 *     constructor?: string,
 *     from_callable?: CallbackType,
 * }
 * @psalm-type AliasType = string|array{
 *     alias: string,
 *     public?: bool,
 *     deprecated?: DeprecationType,
 * }
 * @psalm-type PrototypeType = array{
 *     resource: string,
 *     namespace?: string,
 *     exclude?: string|list<string>,
 *     parent?: string,
 *     shared?: bool,
 *     lazy?: bool|string,
 *     public?: bool,
 *     abstract?: bool,
 *     deprecated?: DeprecationType,
 *     factory?: CallbackType,
 *     arguments?: ArgumentsType,
 *     properties?: array<string, mixed>,
 *     configurator?: CallbackType,
 *     calls?: list<CallType>,
 *     tags?: TagsType,
 *     resource_tags?: TagsType,
 *     autowire?: bool,
 *     autoconfigure?: bool,
 *     bind?: array<string, mixed>,
 *     constructor?: string,
 * }
 * @psalm-type StackType = array{
 *     stack: list<DefinitionType|AliasType|PrototypeType|array<class-string, ArgumentsType|null>>,
 *     public?: bool,
 *     deprecated?: DeprecationType,
 *     decorates?: string,
 *     decorates_tag?: string,
 *     decoration_inner_name?: string,
 *     decoration_priority?: int,
 *     decoration_on_invalid?: 'exception'|'ignore'|null,
 * }
 * @psalm-type ServicesConfig = array{
 *     _defaults?: DefaultsType,
 *     _instanceof?: array<class-string, InstanceofType>,
 *     ...<string, DefinitionType|AliasType|PrototypeType|StackType|ArgumentsType|null>
 * }
 * @psalm-type ExtensionType = array<string, mixed>
 * @psalm-type FrameworkConfig = array{
 *     secret?: scalar|Param|null,
 *     http_method_override?: bool|Param, // Set true to enable support for the '_method' request parameter to determine the intended HTTP method on POST requests. // Default: false
 *     allowed_http_method_override?: null|list<string|Param>,
 *     trust_x_sendfile_type_header?: scalar|Param|null, // Set true to enable support for xsendfile in binary file responses. // Default: "%env(bool:default::SYMFONY_TRUST_X_SENDFILE_TYPE_HEADER)%"
 *     ide?: scalar|Param|null, // Default: "%env(default::SYMFONY_IDE)%"
 *     test?: bool|Param,
 *     default_locale?: scalar|Param|null, // Default: "en"
 *     set_locale_from_accept_language?: bool|Param, // Whether to use the Accept-Language HTTP header to set the Request locale (only when the "_locale" request attribute is not passed). // Default: false
 *     set_content_language_from_locale?: bool|Param, // Whether to set the Content-Language HTTP header on the Response using the Request locale. // Default: false
 *     enabled_locales?: list<scalar|Param|null>,
 *     trusted_hosts?: Param|string|list<scalar|Param|null>,
 *     trusted_proxies?: mixed, // Default: ["%env(default::SYMFONY_TRUSTED_PROXIES)%"]
 *     trusted_headers?: Param|string|list<scalar|Param|null>,
 *     error_controller?: scalar|Param|null, // Default: "error_controller"
 *     handle_all_throwables?: bool|Param, // HttpKernel will handle all kinds of \Throwable. // Default: true
 *     csrf_protection?: bool|array{
 *         enabled?: scalar|Param|null, // Default: null
 *         stateless_token_ids?: list<scalar|Param|null>,
 *         check_header?: scalar|Param|null, // Whether to check the CSRF token in a header in addition to a cookie when using stateless protection. // Default: false
 *         cookie_name?: scalar|Param|null, // The name of the cookie to use when using stateless protection. // Default: "csrf-token"
 *     },
 *     form?: bool|array{ // Form configuration
 *         enabled?: bool|Param, // Default: true
 *         csrf_protection?: bool|array{
 *             enabled?: scalar|Param|null, // Default: null
 *             token_id?: scalar|Param|null, // Default: null
 *             field_name?: scalar|Param|null, // Default: "_token"
 *             field_attr?: array<string, scalar|Param|null>,
 *         },
 *     },
 *     http_cache?: bool|array{ // HTTP cache configuration
 *         enabled?: bool|Param, // Default: false
 *         debug?: bool|Param, // Default: "%kernel.debug%"
 *         trace_level?: "none"|"short"|"full"|Param,
 *         trace_header?: scalar|Param|null,
 *         default_ttl?: int|Param,
 *         private_headers?: list<scalar|Param|null>,
 *         skip_response_headers?: list<scalar|Param|null>,
 *         allow_reload?: bool|Param,
 *         allow_revalidate?: bool|Param,
 *         stale_while_revalidate?: int|Param,
 *         stale_if_error?: int|Param,
 *         terminate_on_cache_hit?: bool|Param, // Deprecated: Setting the "framework.http_cache.terminate_on_cache_hit.terminate_on_cache_hit" configuration option is deprecated. It will be removed in version 9.0.
 *     },
 *     esi?: bool|array{ // ESI configuration
 *         enabled?: bool|Param, // Default: false
 *     },
 *     ssi?: bool|array{ // SSI configuration
 *         enabled?: bool|Param, // Default: false
 *     },
 *     fragments?: bool|array{ // Fragments configuration
 *         enabled?: bool|Param, // Default: false
 *         hinclude_default_template?: scalar|Param|null, // Default: null
 *         path?: scalar|Param|null, // Default: "/_fragment"
 *     },
 *     profiler?: bool|array{ // Profiler configuration
 *         enabled?: bool|Param, // Default: false
 *         collect?: bool|Param, // Default: true
 *         collect_parameter?: scalar|Param|null, // The name of the parameter to use to enable or disable collection on a per request basis. // Default: null
 *         only_exceptions?: bool|Param, // Default: false
 *         only_main_requests?: bool|Param, // Default: false
 *         dsn?: scalar|Param|null, // Default: "file:%kernel.cache_dir%/profiler"
 *         collect_serializer_data?: true|Param, // Deprecated: Setting the "framework.profiler.collect_serializer_data.collect_serializer_data" configuration option is deprecated. It will be removed in version 9.0. // Default: true
 *     },
 *     workflows?: bool|array{
 *         enabled?: bool|Param, // Default: false
 *         workflows?: array<string, array{ // Default: []
 *             audit_trail?: bool|array{
 *                 enabled?: bool|Param, // Default: false
 *             },
 *             type?: "workflow"|"state_machine"|Param, // Default: "state_machine"
 *             marking_store?: array{
 *                 type?: "method"|Param,
 *                 property?: scalar|Param|null,
 *                 service?: scalar|Param|null,
 *             },
 *             supports?: Param|string|list<scalar|Param|null>,
 *             definition_validators?: list<scalar|Param|null>,
 *             support_strategy?: scalar|Param|null,
 *             initial_marking?: \BackedEnum|Param|string|list<scalar|Param|null>,
 *             events_to_dispatch?: null|list<string|Param>,
 *             places?: Param|string|list<array{ // Default: []
 *                 name?: scalar|Param|null,
 *                 metadata?: array<string, mixed>,
 *             }>,
 *             transitions?: list<array{ // Default: []
 *                 name?: string|Param,
 *                 guard?: string|Param, // An expression to block the transition.
 *                 from?: \BackedEnum|Param|string|list<array{ // Default: []
 *                     place?: string|Param,
 *                     weight?: int|Param, // Default: 1
 *                 }>,
 *                 to?: \BackedEnum|Param|string|list<array{ // Default: []
 *                     place?: string|Param,
 *                     weight?: int|Param, // Default: 1
 *                 }>,
 *                 weight?: int|Param, // Default: 1
 *                 metadata?: array<string, mixed>,
 *             }>,
 *             metadata?: array<string, mixed>,
 *         }>,
 *     },
 *     router?: bool|array{ // Router configuration
 *         enabled?: bool|Param, // Default: false
 *         resource?: scalar|Param|null,
 *         type?: scalar|Param|null,
 *         default_uri?: scalar|Param|null, // The default URI used to generate URLs in a non-HTTP context. // Default: null
 *         http_port?: scalar|Param|null, // Default: 80
 *         https_port?: scalar|Param|null, // Default: 443
 *         strict_requirements?: scalar|Param|null, // set to true to throw an exception when a parameter does not match the requirements set to false to disable exceptions when a parameter does not match the requirements (and return null instead) set to null to disable parameter checks against requirements 'true' is the preferred configuration in development mode, while 'false' or 'null' might be preferred in production // Default: true
 *         utf8?: bool|Param, // Default: true
 *     },
 *     session?: bool|array{ // Session configuration
 *         enabled?: bool|Param, // Default: false
 *         storage_factory_id?: scalar|Param|null, // Default: "session.storage.factory.native"
 *         handler_id?: scalar|Param|null, // Defaults to using the native session handler, or to the native *file* session handler if "save_path" is not null.
 *         name?: scalar|Param|null,
 *         cookie_lifetime?: scalar|Param|null,
 *         cookie_path?: scalar|Param|null,
 *         cookie_domain?: scalar|Param|null,
 *         cookie_secure?: true|false|"auto"|Param, // Default: "auto"
 *         cookie_httponly?: bool|Param, // Default: true
 *         cookie_samesite?: null|"lax"|"strict"|"none"|Param, // Default: "lax"
 *         use_cookies?: bool|Param,
 *         gc_divisor?: scalar|Param|null,
 *         gc_probability?: scalar|Param|null,
 *         gc_maxlifetime?: scalar|Param|null,
 *         save_path?: scalar|Param|null, // Defaults to "%kernel.cache_dir%/sessions" if the "handler_id" option is not null.
 *         metadata_update_threshold?: int|Param, // Seconds to wait between 2 session metadata updates. // Default: 0
 *     },
 *     request?: bool|array{ // Request configuration
 *         enabled?: bool|Param, // Default: false
 *         formats?: array<string, Param|string|list<scalar|Param|null>>,
 *     },
 *     assets?: bool|array{ // Assets configuration
 *         enabled?: bool|Param, // Default: true
 *         strict_mode?: bool|Param, // Throw an exception if an entry is missing from the manifest.json. // Default: false
 *         version_strategy?: scalar|Param|null, // Default: null
 *         version?: scalar|Param|null, // Default: null
 *         version_format?: scalar|Param|null, // Default: "%%s?%%s"
 *         json_manifest_path?: scalar|Param|null, // Default: null
 *         base_path?: scalar|Param|null, // Default: ""
 *         base_urls?: Param|string|list<scalar|Param|null>,
 *         packages?: array<string, array{ // Default: []
 *             strict_mode?: bool|Param, // Throw an exception if an entry is missing from the manifest.json. // Default: false
 *             version_strategy?: scalar|Param|null, // Default: null
 *             version?: scalar|Param|null,
 *             version_format?: scalar|Param|null, // Default: null
 *             json_manifest_path?: scalar|Param|null, // Default: null
 *             base_path?: scalar|Param|null, // Default: ""
 *             base_urls?: Param|string|list<scalar|Param|null>,
 *         }>,
 *     },
 *     asset_mapper?: bool|array{ // Asset Mapper configuration
 *         enabled?: bool|Param, // Default: false
 *         paths?: Param|string|array<string, scalar|Param|null>,
 *         excluded_patterns?: list<scalar|Param|null>,
 *         exclude_dotfiles?: bool|Param, // If true, any files starting with "." will be excluded from the asset mapper. // Default: true
 *         server?: bool|Param, // If true, a "dev server" will return the assets from the public directory (true in "debug" mode only by default). // Default: true
 *         public_prefix?: scalar|Param|null, // The public path where the assets will be written to (and served from when "server" is true). // Default: "/assets/"
 *         missing_import_mode?: "strict"|"warn"|"ignore"|Param, // Behavior if an asset cannot be found when imported from JavaScript or CSS files - e.g. "import './non-existent.js'". "strict" means an exception is thrown, "warn" means a warning is logged, "ignore" means the import is left as-is. // Default: "warn"
 *         extensions?: array<string, scalar|Param|null>,
 *         importmap_path?: scalar|Param|null, // The path of the importmap.php file. // Default: "%kernel.project_dir%/importmap.php"
 *         importmap_polyfill?: scalar|Param|null, // The importmap name that will be used to load the polyfill. Set to false to disable. // Default: "es-module-shims"
 *         importmap_script_attributes?: array<string, scalar|Param|null>,
 *         vendor_dir?: scalar|Param|null, // The directory to store JavaScript vendors. // Default: "%kernel.project_dir%/assets/vendor"
 *         precompress?: bool|array{ // Precompress assets with Brotli, Zstandard and gzip.
 *             enabled?: bool|Param, // Default: false
 *             formats?: list<scalar|Param|null>,
 *             extensions?: list<scalar|Param|null>,
 *         },
 *     },
 *     translator?: bool|array{ // Translator configuration
 *         enabled?: bool|Param, // Default: true
 *         fallbacks?: Param|string|list<scalar|Param|null>,
 *         logging?: bool|Param, // Default: false
 *         formatter?: scalar|Param|null, // Default: "translator.formatter.default"
 *         cache_dir?: scalar|Param|null, // Default: "%kernel.cache_dir%/translations"
 *         default_path?: scalar|Param|null, // The default path used to load translations. // Default: "%kernel.project_dir%/translations"
 *         paths?: list<scalar|Param|null>,
 *         pseudo_localization?: bool|array{
 *             enabled?: bool|Param, // Default: false
 *             accents?: bool|Param, // Default: true
 *             expansion_factor?: float|Param, // Default: 1.0
 *             brackets?: bool|Param, // Default: true
 *             parse_html?: bool|Param, // Default: false
 *             localizable_html_attributes?: list<scalar|Param|null>,
 *         },
 *         providers?: array<string, array{ // Default: []
 *             dsn?: scalar|Param|null,
 *             domains?: list<scalar|Param|null>,
 *             locales?: list<scalar|Param|null>,
 *         }>,
 *         globals?: array<string, Param|string|array{ // Default: []
 *             value?: mixed,
 *             message?: string|Param,
 *             parameters?: array<string, scalar|Param|null>,
 *             domain?: string|Param,
 *         }>,
 *     },
 *     validation?: bool|array{ // Validation configuration
 *         enabled?: bool|Param, // Default: true
 *         enable_attributes?: bool|Param, // Default: true
 *         static_method?: Param|string|list<scalar|Param|null>,
 *         translation_domain?: scalar|Param|null, // Default: "validators"
 *         email_validation_mode?: "html5"|"html5-allow-no-tld"|"strict"|Param, // Default: "html5"
 *         mapping?: array{
 *             paths?: list<scalar|Param|null>,
 *         },
 *         not_compromised_password?: bool|array{
 *             enabled?: bool|Param, // When disabled, compromised passwords will be accepted as valid. // Default: true
 *             endpoint?: scalar|Param|null, // API endpoint for the NotCompromisedPassword Validator. // Default: null
 *         },
 *         disable_translation?: bool|Param, // Default: false
 *         property_metadata_existence_check?: bool|Param, // When enabled, validateProperty() and validatePropertyValue() throw an exception if no metadata is found for the given property. // Default: false
 *         auto_mapping?: array<string, array{ // Default: []
 *             services?: list<scalar|Param|null>,
 *         }>,
 *     },
 *     serializer?: bool|array{ // Serializer configuration
 *         enabled?: bool|Param, // Default: false
 *         enable_attributes?: bool|Param, // Default: true
 *         name_converter?: scalar|Param|null,
 *         circular_reference_handler?: scalar|Param|null,
 *         max_depth_handler?: scalar|Param|null,
 *         mapping?: array{
 *             paths?: list<scalar|Param|null>,
 *         },
 *         default_context?: array<string, mixed>,
 *         named_serializers?: array<string, array{ // Default: []
 *             name_converter?: scalar|Param|null,
 *             default_context?: array<string, mixed>,
 *             include_built_in_normalizers?: bool|Param, // Whether to include the built-in normalizers // Default: true
 *             include_built_in_encoders?: bool|Param, // Whether to include the built-in encoders // Default: true
 *         }>,
 *     },
 *     property_access?: bool|array{ // Property access configuration
 *         enabled?: bool|Param, // Default: true
 *         magic_call?: bool|Param, // Default: false
 *         magic_get?: bool|Param, // Default: true
 *         magic_set?: bool|Param, // Default: true
 *         throw_exception_on_invalid_index?: bool|Param, // Default: false
 *         throw_exception_on_invalid_property_path?: bool|Param, // Default: true
 *     },
 *     type_info?: bool|array{ // Type info configuration
 *         enabled?: bool|Param, // Default: true
 *         aliases?: array<string, scalar|Param|null>,
 *     },
 *     property_info?: bool|array{ // Property info configuration
 *         enabled?: bool|Param, // Default: true
 *         with_constructor_extractor?: bool|Param, // Registers the constructor extractor. // Default: true
 *     },
 *     cache?: array{ // Cache configuration
 *         prefix_seed?: scalar|Param|null, // Used to namespace cache keys when using several apps with the same shared backend. // Default: "_%kernel.project_dir%.%kernel.container_class%"
 *         app?: scalar|Param|null, // App related cache pools configuration. // Default: "cache.adapter.filesystem"
 *         system?: scalar|Param|null, // System related cache pools configuration. // Default: "cache.adapter.system"
 *         directory?: scalar|Param|null, // Default: "%kernel.share_dir%/pools/app"
 *         default_psr6_provider?: scalar|Param|null,
 *         default_redis_provider?: scalar|Param|null, // Default: "redis://localhost"
 *         default_valkey_provider?: scalar|Param|null, // Default: "valkey://localhost"
 *         default_memcached_provider?: scalar|Param|null, // Default: "memcached://localhost"
 *         default_doctrine_dbal_provider?: scalar|Param|null, // Default: "database_connection"
 *         default_pdo_provider?: scalar|Param|null, // Default: null
 *         pools?: array<string, array{ // Default: []
 *             adapters?: Param|string|list<scalar|Param|null>,
 *             tags?: scalar|Param|null, // Default: null
 *             public?: bool|Param, // Default: false
 *             default_lifetime?: scalar|Param|null, // Default lifetime of the pool.
 *             provider?: scalar|Param|null, // Overwrite the setting from the default provider for this adapter.
 *             early_expiration_message_bus?: scalar|Param|null,
 *             clearer?: scalar|Param|null,
 *             marshaller?: scalar|Param|null, // The marshaller service to use for this pool.
 *         }>,
 *     },
 *     php_errors?: array{ // PHP errors handling configuration
 *         log?: mixed, // Use the application logger instead of the PHP logger for logging PHP errors. // Default: true
 *         throw?: bool|Param, // Throw PHP errors as \ErrorException instances. // Default: true
 *     },
 *     exceptions?: array<string, array{ // Default: []
 *         log_level?: scalar|Param|null, // The level of log message. Null to let Symfony decide. // Default: null
 *         status_code?: scalar|Param|null, // The status code of the response. Null or 0 to let Symfony decide. // Default: null
 *         log_channel?: scalar|Param|null, // The channel of log message. Null to let Symfony decide. // Default: null
 *     }>,
 *     web_link?: bool|array{ // Web links configuration
 *         enabled?: bool|Param, // Default: false
 *     },
 *     lock?: Param|bool|string|array{ // Lock configuration
 *         enabled?: bool|Param, // Default: false
 *         resources?: Param|string|array<string, Param|string|list<scalar|Param|null>>,
 *     },
 *     semaphore?: Param|bool|string|array{ // Semaphore configuration
 *         enabled?: bool|Param, // Default: false
 *         resources?: Param|string|array<string, scalar|Param|null>,
 *     },
 *     messenger?: bool|array{ // Messenger configuration
 *         enabled?: bool|Param, // Default: false
 *         routing?: array<string, Param|string|list<scalar|Param|null>>,
 *         serializer?: array{
 *             default_serializer?: scalar|Param|null, // Service id to use as the default serializer for the transports. // Default: "messenger.transport.native_php_serializer"
 *             symfony_serializer?: array{
 *                 format?: scalar|Param|null, // Serialization format for the messenger.transport.symfony_serializer service (which is not the serializer used by default). // Default: "json"
 *                 context?: array<string, mixed>,
 *             },
 *         },
 *         transports?: array<string, Param|string|array{ // Default: []
 *             dsn?: scalar|Param|null,
 *             serializer?: scalar|Param|null, // Service id of a custom serializer to use. // Default: null
 *             options?: array<string, mixed>,
 *             failure_transport?: scalar|Param|null, // Transport name to send failed messages to (after all retries have failed). // Default: null
 *             retry_strategy?: Param|string|array{
 *                 service?: scalar|Param|null, // Service id to override the retry strategy entirely. // Default: null
 *                 max_retries?: int|Param, // Default: 3
 *                 delay?: int|Param, // Time in ms to delay (or the initial value when multiplier is used). // Default: 1000
 *                 multiplier?: float|Param, // If greater than 1, delay will grow exponentially for each retry: this delay = (delay * (multiple ^ retries)). // Default: 2
 *                 max_delay?: int|Param, // Max time in ms that a retry should ever be delayed (0 = infinite). // Default: 0
 *                 jitter?: float|Param, // Randomness to apply to the delay (between 0 and 1). // Default: 0.1
 *             },
 *             rate_limiter?: scalar|Param|null, // Rate limiter name to use when processing messages. // Default: null
 *         }>,
 *         failure_transport?: scalar|Param|null, // Transport name to send failed messages to (after all retries have failed). // Default: null
 *         stop_worker_on_signals?: Param|int|string|list<scalar|Param|null>,
 *         default_bus?: scalar|Param|null, // Default: null
 *         buses?: array<string, array{ // Default: {"messenger.bus.default":{"default_middleware":{"enabled":true,"allow_no_handlers":false,"allow_no_senders":true},"middleware":[]}}
 *             default_middleware?: Param|bool|string|array{
 *                 enabled?: bool|Param, // Default: true
 *                 allow_no_handlers?: bool|Param, // Default: false
 *                 allow_no_senders?: bool|Param, // Default: true
 *             },
 *             middleware?: Param|string|list<Param|string|array{ // Default: []
 *                 id?: scalar|Param|null,
 *                 arguments?: list<mixed>,
 *             }>,
 *         }>,
 *     },
 *     scheduler?: bool|array{ // Scheduler configuration
 *         enabled?: bool|Param, // Default: false
 *     },
 *     disallow_search_engine_index?: bool|Param, // Enabled by default when debug is enabled. // Default: true
 *     http_client?: bool|array{ // HTTP Client configuration
 *         enabled?: bool|Param, // Default: false
 *         max_host_connections?: int|Param, // The maximum number of connections to a single host.
 *         default_options?: array{
 *             headers?: array<string, mixed>,
 *             vars?: array<string, mixed>,
 *             max_redirects?: int|Param, // The maximum number of redirects to follow.
 *             http_version?: scalar|Param|null, // The default HTTP version, typically 1.1 or 2.0, leave to null for the best version.
 *             resolve?: array<string, scalar|Param|null>,
 *             proxy?: scalar|Param|null, // The URL of the proxy to pass requests through or null for automatic detection.
 *             no_proxy?: scalar|Param|null, // A comma separated list of hosts that do not require a proxy to be reached.
 *             timeout?: float|Param, // The idle timeout, defaults to the "default_socket_timeout" ini parameter.
 *             max_duration?: float|Param, // The maximum execution time for the request+response as a whole.
 *             bindto?: scalar|Param|null, // A network interface name, IP address, a host name or a UNIX socket to bind to.
 *             verify_peer?: bool|Param, // Indicates if the peer should be verified in a TLS context.
 *             verify_host?: bool|Param, // Indicates if the host should exist as a certificate common name.
 *             cafile?: scalar|Param|null, // A certificate authority file.
 *             capath?: scalar|Param|null, // A directory that contains multiple certificate authority files.
 *             local_cert?: scalar|Param|null, // A PEM formatted certificate file.
 *             local_pk?: scalar|Param|null, // A private key file.
 *             passphrase?: scalar|Param|null, // The passphrase used to encrypt the "local_pk" file.
 *             ciphers?: scalar|Param|null, // A list of TLS ciphers separated by colons, commas or spaces (e.g. "RC3-SHA:TLS13-AES-128-GCM-SHA256"...)
 *             peer_fingerprint?: array{ // Associative array: hashing algorithm => hash(es).
 *                 sha1?: mixed,
 *                 pin-sha256?: mixed,
 *                 md5?: mixed,
 *             },
 *             crypto_method?: scalar|Param|null, // The minimum version of TLS to accept; must be one of STREAM_CRYPTO_METHOD_TLSv*_CLIENT constants.
 *             extra?: array<string, mixed>,
 *             rate_limiter?: scalar|Param|null, // Rate limiter name to use for throttling requests. // Default: null
 *             caching?: bool|array{ // Caching configuration.
 *                 enabled?: bool|Param, // Default: false
 *                 cache_pool?: string|Param, // The taggable cache pool to use for storing the responses. // Default: "cache.http_client"
 *                 shared?: bool|Param, // Indicates whether the cache is shared (public) or private. // Default: true
 *                 max_ttl?: int|Param, // The maximum TTL (in seconds) allowed for cached responses. // Default: 86400
 *             },
 *             retry_failed?: bool|array{
 *                 enabled?: bool|Param, // Default: false
 *                 retry_strategy?: scalar|Param|null, // service id to override the retry strategy. // Default: null
 *                 http_codes?: Param|int|string|array<string, array{ // Default: []
 *                     code?: int|Param,
 *                     methods?: Param|string|list<string|Param>,
 *                 }>,
 *                 max_retries?: int|Param, // Default: 3
 *                 delay?: int|Param, // Time in ms to delay (or the initial value when multiplier is used). // Default: 1000
 *                 multiplier?: float|Param, // If greater than 1, delay will grow exponentially for each retry: delay * (multiple ^ retries). // Default: 2
 *                 max_delay?: int|Param, // Max time in ms that a retry should ever be delayed (0 = infinite). // Default: 0
 *                 jitter?: float|Param, // Randomness in percent (between 0 and 1) to apply to the delay. // Default: 0.1
 *             },
 *         },
 *         mock_response_factory?: scalar|Param|null, // `true` to always return empty 200 responses, or the id of the service to use to generate mock responses - which should be either an invokable or an iterable.
 *         scoped_clients?: array<string, Param|string|array{ // Default: []
 *             scope?: scalar|Param|null, // The regular expression that the request URL must match before adding the other options. When none is provided, the base URI is used instead.
 *             base_uri?: scalar|Param|null, // The URI to resolve relative URLs, following rules in RFC 3985, section 2.
 *             auth_basic?: scalar|Param|null, // An HTTP Basic authentication "username:password".
 *             auth_bearer?: scalar|Param|null, // A token enabling HTTP Bearer authorization.
 *             auth_ntlm?: scalar|Param|null, // A "username:password" pair to use Microsoft NTLM authentication (requires the cURL extension).
 *             query?: array<string, scalar|Param|null>,
 *             headers?: array<string, mixed>,
 *             max_redirects?: int|Param, // The maximum number of redirects to follow.
 *             http_version?: scalar|Param|null, // The default HTTP version, typically 1.1 or 2.0, leave to null for the best version.
 *             resolve?: array<string, scalar|Param|null>,
 *             proxy?: scalar|Param|null, // The URL of the proxy to pass requests through or null for automatic detection.
 *             no_proxy?: scalar|Param|null, // A comma separated list of hosts that do not require a proxy to be reached.
 *             timeout?: float|Param, // The idle timeout, defaults to the "default_socket_timeout" ini parameter.
 *             max_duration?: float|Param, // The maximum execution time for the request+response as a whole.
 *             bindto?: scalar|Param|null, // A network interface name, IP address, a host name or a UNIX socket to bind to.
 *             verify_peer?: bool|Param, // Indicates if the peer should be verified in a TLS context.
 *             verify_host?: bool|Param, // Indicates if the host should exist as a certificate common name.
 *             cafile?: scalar|Param|null, // A certificate authority file.
 *             capath?: scalar|Param|null, // A directory that contains multiple certificate authority files.
 *             local_cert?: scalar|Param|null, // A PEM formatted certificate file.
 *             local_pk?: scalar|Param|null, // A private key file.
 *             passphrase?: scalar|Param|null, // The passphrase used to encrypt the "local_pk" file.
 *             ciphers?: scalar|Param|null, // A list of TLS ciphers separated by colons, commas or spaces (e.g. "RC3-SHA:TLS13-AES-128-GCM-SHA256"...).
 *             peer_fingerprint?: array{ // Associative array: hashing algorithm => hash(es).
 *                 sha1?: mixed,
 *                 pin-sha256?: mixed,
 *                 md5?: mixed,
 *             },
 *             crypto_method?: scalar|Param|null, // The minimum version of TLS to accept; must be one of STREAM_CRYPTO_METHOD_TLSv*_CLIENT constants.
 *             mock_response_factory?: scalar|Param|null, // `true` to always return empty 200 responses, `false` to disable mocking, or the id of the service to use to generate mock responses (invokable or iterable).
 *             extra?: array<string, mixed>,
 *             rate_limiter?: scalar|Param|null, // Rate limiter name to use for throttling requests. // Default: null
 *             caching?: bool|array{ // Caching configuration.
 *                 enabled?: bool|Param, // Default: false
 *                 cache_pool?: string|Param, // The taggable cache pool to use for storing the responses. // Default: "cache.http_client"
 *                 shared?: bool|Param, // Indicates whether the cache is shared (public) or private. // Default: true
 *                 max_ttl?: int|Param, // The maximum TTL (in seconds) allowed for cached responses. // Default: 86400
 *             },
 *             retry_failed?: bool|array{
 *                 enabled?: bool|Param, // Default: false
 *                 retry_strategy?: scalar|Param|null, // service id to override the retry strategy. // Default: null
 *                 http_codes?: Param|int|string|array<string, array{ // Default: []
 *                     code?: int|Param,
 *                     methods?: Param|string|list<string|Param>,
 *                 }>,
 *                 max_retries?: int|Param, // Default: 3
 *                 delay?: int|Param, // Time in ms to delay (or the initial value when multiplier is used). // Default: 1000
 *                 multiplier?: float|Param, // If greater than 1, delay will grow exponentially for each retry: delay * (multiple ^ retries). // Default: 2
 *                 max_delay?: int|Param, // Max time in ms that a retry should ever be delayed (0 = infinite). // Default: 0
 *                 jitter?: float|Param, // Randomness in percent (between 0 and 1) to apply to the delay. // Default: 0.1
 *             },
 *         }>,
 *     },
 *     mailer?: bool|array{ // Mailer configuration
 *         enabled?: bool|Param, // Default: false
 *         message_bus?: scalar|Param|null, // The message bus to use. Defaults to the default bus if the Messenger component is installed. // Default: null
 *         dsn?: scalar|Param|null, // Default: null
 *         transports?: array<string, scalar|Param|null>,
 *         envelope?: array{ // Mailer Envelope configuration
 *             sender?: scalar|Param|null,
 *             recipients?: Param|string|list<scalar|Param|null>,
 *             allowed_recipients?: Param|string|list<scalar|Param|null>,
 *         },
 *         headers?: array<string, Param|string|array{ // Default: []
 *             value?: mixed,
 *         }>,
 *         dkim_signer?: bool|array{ // DKIM signer configuration
 *             enabled?: bool|Param, // Default: false
 *             key?: scalar|Param|null, // Key content, or path to key (in PEM format with the `file://` prefix) // Default: ""
 *             domain?: scalar|Param|null, // Default: ""
 *             select?: scalar|Param|null, // Default: ""
 *             passphrase?: scalar|Param|null, // The private key passphrase // Default: ""
 *             options?: array<string, mixed>,
 *         },
 *         smime_signer?: bool|array{ // S/MIME signer configuration
 *             enabled?: bool|Param, // Default: false
 *             key?: scalar|Param|null, // Path to key (in PEM format) // Default: ""
 *             certificate?: scalar|Param|null, // Path to certificate (in PEM format without the `file://` prefix) // Default: ""
 *             passphrase?: scalar|Param|null, // The private key passphrase // Default: null
 *             extra_certificates?: scalar|Param|null, // Default: null
 *             sign_options?: int|Param, // Default: null
 *         },
 *         smime_encrypter?: bool|array{ // S/MIME encrypter configuration
 *             enabled?: bool|Param, // Default: false
 *             repository?: scalar|Param|null, // S/MIME certificate repository service. This service shall implement the `Symfony\Component\Mailer\EventListener\SmimeCertificateRepositoryInterface`. // Default: ""
 *             cipher?: int|Param, // A set of algorithms used to encrypt the message // Default: null
 *         },
 *     },
 *     secrets?: bool|array{
 *         enabled?: bool|Param, // Default: true
 *         vault_directory?: scalar|Param|null, // Default: "%kernel.project_dir%/config/secrets/%kernel.runtime_environment%"
 *         local_dotenv_file?: scalar|Param|null, // Default: "%kernel.project_dir%/.env.%kernel.environment%.local"
 *         decryption_env_var?: scalar|Param|null, // Default: "base64:default::SYMFONY_DECRYPTION_SECRET"
 *     },
 *     notifier?: bool|array{ // Notifier configuration
 *         enabled?: bool|Param, // Default: false
 *         message_bus?: scalar|Param|null, // The message bus to use. Defaults to the default bus if the Messenger component is installed. // Default: null
 *         chatter_transports?: array<string, scalar|Param|null>,
 *         texter_transports?: array<string, scalar|Param|null>,
 *         notification_on_failed_messages?: bool|Param, // Default: false
 *         channel_policy?: array<string, Param|string|list<scalar|Param|null>>,
 *         admin_recipients?: list<array{ // Default: []
 *             email?: scalar|Param|null,
 *             phone?: scalar|Param|null, // Default: ""
 *         }>,
 *     },
 *     rate_limiter?: bool|array{ // Rate limiter configuration
 *         enabled?: bool|Param, // Default: false
 *         limiters?: array<string, array{ // Default: []
 *             lock_factory?: scalar|Param|null, // The service ID of the lock factory used by this limiter (or null to disable locking). // Default: "auto"
 *             cache_pool?: scalar|Param|null, // The cache pool to use for storing the current limiter state. // Default: "cache.rate_limiter"
 *             storage_service?: scalar|Param|null, // The service ID of a custom storage implementation, this precedes any configured "cache_pool". // Default: null
 *             policy?: "fixed_window"|"token_bucket"|"sliding_window"|"compound"|"no_limit"|Param, // The algorithm to be used by this limiter.
 *             limiters?: Param|string|list<scalar|Param|null>,
 *             limit?: int|Param, // The maximum allowed hits in a fixed interval or burst.
 *             interval?: scalar|Param|null, // Configures the fixed interval if "policy" is set to "fixed_window" or "sliding_window". The value must be a number followed by "second", "minute", "hour", "day", "week" or "month" (or their plural equivalent).
 *             rate?: array{ // Configures the fill rate if "policy" is set to "token_bucket".
 *                 interval?: scalar|Param|null, // Configures the rate interval. The value must be a number followed by "second", "minute", "hour", "day", "week" or "month" (or their plural equivalent).
 *                 amount?: int|Param, // Amount of tokens to add each interval. // Default: 1
 *             },
 *             anchor_at?: scalar|Param|null, // Aligns the "fixed_window" policy to a calendar (e.g. "2024-01-05 00:00:00 UTC" combined with `interval: 1 month` resets the counter on the 5th of each month). UTC if not specified. // Default: null
 *         }>,
 *     },
 *     uid?: bool|array{ // Uid configuration
 *         enabled?: bool|Param, // Default: false
 *         default_uuid_version?: 7|6|4|1|Param, // Default: 7
 *         name_based_uuid_version?: 5|3|Param, // Default: 5
 *         name_based_uuid_namespace?: scalar|Param|null,
 *         time_based_uuid_version?: 7|6|1|Param, // Default: 7
 *         time_based_uuid_node?: scalar|Param|null,
 *         uuid47_secret?: scalar|Param|null, // A high-entropy secret used by the "uuid47_transformer" service. Defaults to "kernel.secret". // Default: null
 *     },
 *     html_sanitizer?: bool|array{ // HtmlSanitizer configuration
 *         enabled?: bool|Param, // Default: false
 *         sanitizers?: array<string, array{ // Default: []
 *             default_action?: "drop"|"block"|"allow"|Param, // Defines how the sanitizer must behave by default.
 *             allow_safe_elements?: bool|Param, // Allows "safe" elements and attributes. // Default: false
 *             allow_static_elements?: bool|Param, // Allows all static elements and attributes from the W3C Sanitizer API standard. // Default: false
 *             allow_elements?: array<string, mixed>,
 *             block_elements?: Param|string|list<string|Param>,
 *             drop_elements?: Param|string|list<string|Param>,
 *             allow_attributes?: array<string, mixed>,
 *             drop_attributes?: array<string, mixed>,
 *             force_attributes?: array<string, array<string, string|Param>>,
 *             force_https_urls?: bool|Param, // Transforms URLs using the HTTP scheme to use the HTTPS scheme instead. // Default: false
 *             allowed_link_schemes?: Param|string|list<string|Param>,
 *             allowed_link_hosts?: Param|null|string|list<string|Param>,
 *             allow_relative_links?: bool|Param, // Allows relative URLs to be used in links href attributes. // Default: false
 *             allowed_media_schemes?: Param|string|list<string|Param>,
 *             allowed_media_hosts?: Param|null|string|list<string|Param>,
 *             allow_relative_medias?: bool|Param, // Allows relative URLs to be used in media source attributes (img, audio, video, ...). // Default: false
 *             with_attribute_sanitizers?: Param|string|list<string|Param>,
 *             without_attribute_sanitizers?: Param|string|list<string|Param>,
 *             max_input_length?: int|Param, // The maximum length allowed for the sanitized input. // Default: 0
 *         }>,
 *     },
 *     webhook?: bool|array{ // Webhook configuration
 *         enabled?: bool|Param, // Default: false
 *         message_bus?: scalar|Param|null, // The message bus to use. // Default: "messenger.default_bus"
 *         event_header_name?: scalar|Param|null, // Default: "Webhook-Event"
 *         id_header_name?: scalar|Param|null, // Default: "Webhook-Id"
 *         signature_header_name?: scalar|Param|null, // Default: "Webhook-Signature"
 *         signing_algorithm?: scalar|Param|null, // Default: "sha256"
 *         routing?: array<string, array{ // Default: []
 *             service?: scalar|Param|null,
 *             secret?: scalar|Param|null, // The secret used to verify incoming request signatures. It must be set in production: with an empty value, depending on the parser, requests from any sender are accepted or every request is rejected. // Default: ""
 *         }>,
 *     },
 *     remote_event?: bool|array{ // RemoteEvent configuration
 *         enabled?: bool|Param, // Default: false
 *     },
 *     json_streamer?: bool|array{ // JSON streamer configuration
 *         enabled?: bool|Param, // Default: false
 *         default_options?: array{
 *             include_null_properties?: bool|Param, // Encode the properties with null value // Default: false
 *             ...<string, mixed>
 *         },
 *     },
 * }
 * @psalm-type DoctrineConfig = array{
 *     dbal?: array{
 *         default_connection?: scalar|Param|null,
 *         types?: array<string, Param|string|array{ // Default: []
 *             class?: scalar|Param|null,
 *         }>,
 *         driver_schemes?: array<string, scalar|Param|null>,
 *         connections?: array<string, array{ // Default: []
 *             url?: scalar|Param|null, // A URL with connection information; any parameter value parsed from this string will override explicitly set parameters
 *             dbname?: scalar|Param|null,
 *             host?: scalar|Param|null, // Defaults to "localhost" at runtime.
 *             port?: scalar|Param|null, // Defaults to null at runtime.
 *             user?: scalar|Param|null, // Defaults to "root" at runtime.
 *             password?: scalar|Param|null, // Defaults to null at runtime.
 *             dbname_suffix?: scalar|Param|null, // Adds the given suffix to the configured database name, this option has no effects for the SQLite platform
 *             application_name?: scalar|Param|null,
 *             charset?: scalar|Param|null,
 *             path?: scalar|Param|null,
 *             memory?: bool|Param,
 *             unix_socket?: scalar|Param|null, // The unix socket to use for MySQL
 *             persistent?: bool|Param, // True to use as persistent connection for the ibm_db2 driver
 *             protocol?: scalar|Param|null, // The protocol to use for the ibm_db2 driver (default to TCPIP if omitted)
 *             service?: bool|Param, // True to use SERVICE_NAME as connection parameter instead of SID for Oracle
 *             servicename?: scalar|Param|null, // Overrules dbname parameter if given and used as SERVICE_NAME or SID connection parameter for Oracle depending on the service parameter.
 *             sessionMode?: scalar|Param|null, // The session mode to use for the oci8 driver
 *             server?: scalar|Param|null, // The name of a running database server to connect to for SQL Anywhere.
 *             default_dbname?: scalar|Param|null, // Override the default database (postgres) to connect to for PostgreSQL connection.
 *             sslmode?: scalar|Param|null, // Determines whether or with what priority a SSL TCP/IP connection will be negotiated with the server for PostgreSQL.
 *             sslrootcert?: scalar|Param|null, // The name of a file containing SSL certificate authority (CA) certificate(s). If the file exists, the server's certificate will be verified to be signed by one of these authorities.
 *             sslcert?: scalar|Param|null, // The path to the SSL client certificate file for PostgreSQL.
 *             sslkey?: scalar|Param|null, // The path to the SSL client key file for PostgreSQL.
 *             sslcrl?: scalar|Param|null, // The file name of the SSL certificate revocation list for PostgreSQL.
 *             pooled?: bool|Param, // True to use a pooled server with the oci8/pdo_oracle driver
 *             MultipleActiveResultSets?: bool|Param, // Configuring MultipleActiveResultSets for the pdo_sqlsrv driver
 *             instancename?: scalar|Param|null, // Optional parameter, complete whether to add the INSTANCE_NAME parameter in the connection. It is generally used to connect to an Oracle RAC server to select the name of a particular instance.
 *             connectstring?: scalar|Param|null, // Complete Easy Connect connection descriptor, see https://docs.oracle.com/database/121/NETAG/naming.htm.When using this option, you will still need to provide the user and password parameters, but the other parameters will no longer be used. Note that when using this parameter, the getHost and getPort methods from Doctrine\DBAL\Connection will no longer function as expected.
 *             driver?: scalar|Param|null, // Default: "pdo_mysql"
 *             auto_commit?: bool|Param,
 *             schema_filter?: scalar|Param|null,
 *             logging?: bool|Param, // Default: true
 *             profiling?: bool|Param, // Default: true
 *             profiling_collect_backtrace?: bool|Param, // Enables collecting backtraces when profiling is enabled // Default: false
 *             profiling_collect_schema_errors?: bool|Param, // Enables collecting schema errors when profiling is enabled // Default: true
 *             server_version?: scalar|Param|null,
 *             idle_connection_ttl?: int|Param, // Default: 600
 *             driver_class?: scalar|Param|null,
 *             wrapper_class?: scalar|Param|null,
 *             keep_replica?: bool|Param,
 *             options?: array<string, mixed>,
 *             mapping_types?: array<string, scalar|Param|null>,
 *             default_table_options?: array<string, scalar|Param|null>,
 *             schema_manager_factory?: scalar|Param|null, // Default: "doctrine.dbal.default_schema_manager_factory"
 *             result_cache?: scalar|Param|null,
 *             replicas?: array<string, array{ // Default: []
 *                 url?: scalar|Param|null, // A URL with connection information; any parameter value parsed from this string will override explicitly set parameters
 *                 dbname?: scalar|Param|null,
 *                 host?: scalar|Param|null, // Defaults to "localhost" at runtime.
 *                 port?: scalar|Param|null, // Defaults to null at runtime.
 *                 user?: scalar|Param|null, // Defaults to "root" at runtime.
 *                 password?: scalar|Param|null, // Defaults to null at runtime.
 *                 dbname_suffix?: scalar|Param|null, // Adds the given suffix to the configured database name, this option has no effects for the SQLite platform
 *                 application_name?: scalar|Param|null,
 *                 charset?: scalar|Param|null,
 *                 path?: scalar|Param|null,
 *                 memory?: bool|Param,
 *                 unix_socket?: scalar|Param|null, // The unix socket to use for MySQL
 *                 persistent?: bool|Param, // True to use as persistent connection for the ibm_db2 driver
 *                 protocol?: scalar|Param|null, // The protocol to use for the ibm_db2 driver (default to TCPIP if omitted)
 *                 service?: bool|Param, // True to use SERVICE_NAME as connection parameter instead of SID for Oracle
 *                 servicename?: scalar|Param|null, // Overrules dbname parameter if given and used as SERVICE_NAME or SID connection parameter for Oracle depending on the service parameter.
 *                 sessionMode?: scalar|Param|null, // The session mode to use for the oci8 driver
 *                 server?: scalar|Param|null, // The name of a running database server to connect to for SQL Anywhere.
 *                 default_dbname?: scalar|Param|null, // Override the default database (postgres) to connect to for PostgreSQL connection.
 *                 sslmode?: scalar|Param|null, // Determines whether or with what priority a SSL TCP/IP connection will be negotiated with the server for PostgreSQL.
 *                 sslrootcert?: scalar|Param|null, // The name of a file containing SSL certificate authority (CA) certificate(s). If the file exists, the server's certificate will be verified to be signed by one of these authorities.
 *                 sslcert?: scalar|Param|null, // The path to the SSL client certificate file for PostgreSQL.
 *                 sslkey?: scalar|Param|null, // The path to the SSL client key file for PostgreSQL.
 *                 sslcrl?: scalar|Param|null, // The file name of the SSL certificate revocation list for PostgreSQL.
 *                 pooled?: bool|Param, // True to use a pooled server with the oci8/pdo_oracle driver
 *                 MultipleActiveResultSets?: bool|Param, // Configuring MultipleActiveResultSets for the pdo_sqlsrv driver
 *                 instancename?: scalar|Param|null, // Optional parameter, complete whether to add the INSTANCE_NAME parameter in the connection. It is generally used to connect to an Oracle RAC server to select the name of a particular instance.
 *                 connectstring?: scalar|Param|null, // Complete Easy Connect connection descriptor, see https://docs.oracle.com/database/121/NETAG/naming.htm.When using this option, you will still need to provide the user and password parameters, but the other parameters will no longer be used. Note that when using this parameter, the getHost and getPort methods from Doctrine\DBAL\Connection will no longer function as expected.
 *                 ...<string, mixed>
 *             }>,
 *             ...<string, mixed>
 *         }>,
 *         ...<string, mixed>
 *     },
 *     orm?: array{
 *         default_entity_manager?: scalar|Param|null,
 *         enable_native_lazy_objects?: bool|Param, // Deprecated: The "enable_native_lazy_objects" option is deprecated and will be removed in DoctrineBundle 4.0, as native lazy objects are now always enabled. // Default: true
 *         controller_resolver?: bool|array{
 *             enabled?: bool|Param, // Default: true
 *             auto_mapping?: bool|Param, // Deprecated: The "doctrine.orm.controller_resolver.auto_mapping.auto_mapping" option is deprecated and will be removed in DoctrineBundle 4.0, as it only accepts `false` since 3.0. // Set to true to enable using route placeholders as lookup criteria when the primary key doesn't match the argument name // Default: false
 *             evict_cache?: bool|Param, // Set to true to fetch the entity from the database instead of using the cache, if any // Default: false
 *         },
 *         entity_managers?: array<string, array{ // Default: []
 *             query_cache_driver?: Param|string|array{
 *                 type?: scalar|Param|null, // Default: null
 *                 id?: scalar|Param|null,
 *                 pool?: scalar|Param|null,
 *             },
 *             metadata_cache_driver?: Param|string|array{
 *                 type?: scalar|Param|null, // Default: null
 *                 id?: scalar|Param|null,
 *                 pool?: scalar|Param|null,
 *             },
 *             result_cache_driver?: Param|string|array{
 *                 type?: scalar|Param|null, // Default: null
 *                 id?: scalar|Param|null,
 *                 pool?: scalar|Param|null,
 *             },
 *             entity_listeners?: array{
 *                 entities?: array<string, array{ // Default: []
 *                     listeners?: array<string, array{ // Default: []
 *                         events?: list<array{ // Default: []
 *                             type?: scalar|Param|null,
 *                             method?: scalar|Param|null, // Default: null
 *                         }>,
 *                     }>,
 *                 }>,
 *                 ...<string, mixed>
 *             },
 *             connection?: scalar|Param|null,
 *             class_metadata_factory_name?: scalar|Param|null, // Default: "Doctrine\\ORM\\Mapping\\ClassMetadataFactory"
 *             default_repository_class?: scalar|Param|null, // Default: "Doctrine\\ORM\\EntityRepository"
 *             auto_mapping?: scalar|Param|null, // Default: false
 *             naming_strategy?: scalar|Param|null, // Default: "doctrine.orm.naming_strategy.default"
 *             quote_strategy?: scalar|Param|null, // Default: "doctrine.orm.quote_strategy.default"
 *             typed_field_mapper?: scalar|Param|null, // Default: "doctrine.orm.typed_field_mapper.default"
 *             entity_listener_resolver?: scalar|Param|null, // Default: null
 *             fetch_mode_subselect_batch_size?: scalar|Param|null,
 *             repository_factory?: scalar|Param|null, // Default: "doctrine.orm.container_repository_factory"
 *             schema_ignore_classes?: list<scalar|Param|null>,
 *             validate_xml_mapping?: bool|Param, // Set to "true" to opt-in to the new mapping driver mode that was added in Doctrine ORM 2.14 and will be mandatory in ORM 3.0. See https://github.com/doctrine/orm/pull/6728. // Default: false
 *             second_level_cache?: array{
 *                 region_cache_driver?: Param|string|array{
 *                     type?: scalar|Param|null, // Default: null
 *                     id?: scalar|Param|null,
 *                     pool?: scalar|Param|null,
 *                 },
 *                 region_lock_lifetime?: scalar|Param|null, // Default: 60
 *                 log_enabled?: bool|Param, // Default: true
 *                 region_lifetime?: scalar|Param|null, // Default: 3600
 *                 enabled?: bool|Param, // Default: true
 *                 factory?: scalar|Param|null,
 *                 regions?: array<string, array{ // Default: []
 *                     cache_driver?: Param|string|array{
 *                         type?: scalar|Param|null, // Default: null
 *                         id?: scalar|Param|null,
 *                         pool?: scalar|Param|null,
 *                     },
 *                     lock_path?: scalar|Param|null, // Default: "%kernel.cache_dir%/doctrine/orm/slc/filelock"
 *                     lock_lifetime?: scalar|Param|null, // Default: 60
 *                     type?: scalar|Param|null, // Default: "default"
 *                     lifetime?: scalar|Param|null, // Default: null
 *                     service?: scalar|Param|null,
 *                     name?: scalar|Param|null,
 *                 }>,
 *                 loggers?: array<string, array{ // Default: []
 *                     name?: scalar|Param|null,
 *                     service?: scalar|Param|null,
 *                 }>,
 *             },
 *             hydrators?: array<string, scalar|Param|null>,
 *             mappings?: array<string, Param|bool|string|array{ // Default: []
 *                 mapping?: scalar|Param|null, // Default: true
 *                 type?: scalar|Param|null,
 *                 dir?: scalar|Param|null,
 *                 alias?: scalar|Param|null,
 *                 prefix?: scalar|Param|null,
 *                 is_bundle?: bool|Param,
 *             }>,
 *             dql?: array{
 *                 string_functions?: array<string, scalar|Param|null>,
 *                 numeric_functions?: array<string, scalar|Param|null>,
 *                 datetime_functions?: array<string, scalar|Param|null>,
 *             },
 *             filters?: array<string, Param|string|array{ // Default: []
 *                 class?: scalar|Param|null,
 *                 enabled?: bool|Param, // Default: false
 *                 parameters?: array<string, mixed>,
 *                 ...<string, mixed>
 *             }>,
 *             identity_generation_preferences?: array<string, scalar|Param|null>,
 *         }>,
 *         resolve_target_entities?: array<string, scalar|Param|null>,
 *         ...<string, mixed>
 *     },
 * }
 * @psalm-type TwigConfig = array{
 *     form_themes?: list<scalar|Param|null>,
 *     globals?: array<string, array{ // Default: []
 *         id?: scalar|Param|null,
 *         type?: scalar|Param|null,
 *         value?: mixed,
 *         ...<string, mixed>
 *     }>,
 *     autoescape_service?: scalar|Param|null, // Default: null
 *     autoescape_service_method?: scalar|Param|null, // Default: null
 *     cache?: scalar|Param|null, // Default: true
 *     charset?: scalar|Param|null, // Default: "%kernel.charset%"
 *     debug?: bool|Param, // Default: "%kernel.debug%"
 *     strict_variables?: bool|Param, // Default: "%kernel.debug%"
 *     auto_reload?: scalar|Param|null,
 *     optimizations?: int|Param,
 *     default_path?: scalar|Param|null, // The default path used to load templates. // Default: "%kernel.project_dir%/templates"
 *     file_name_pattern?: Param|string|list<scalar|Param|null>,
 *     paths?: array<string, mixed>,
 *     date?: array{ // The default format options used by the date filter.
 *         format?: scalar|Param|null, // Default: "F j, Y H:i"
 *         interval_format?: scalar|Param|null, // Default: "%d days"
 *         timezone?: scalar|Param|null, // The timezone used when formatting dates, when set to null, the timezone returned by date_default_timezone_get() is used. // Default: null
 *     },
 *     number_format?: array{ // The default format options for the number_format filter.
 *         decimals?: int|Param, // Default: 0
 *         decimal_point?: scalar|Param|null, // Default: "."
 *         thousands_separator?: scalar|Param|null, // Default: ","
 *     },
 *     mailer?: array{
 *         html_to_text_converter?: scalar|Param|null, // A service implementing the "Symfony\Component\Mime\HtmlToTextConverter\HtmlToTextConverterInterface". // Default: null
 *     },
 * }
 * @psalm-type NowoDoctrineEncryptConfig = array{
 *     encrypted_fields?: array<string, array{ // Default: []
 *         properties?: array<string, scalar|Param|null>,
 *         embedded?: Param|string|list<scalar|Param|null>,
 *     }>,
 *     property_access?: "auto"|"reflection"|Param, // How the listener reads and writes encrypted properties: "auto" (default) = getter/one-argument setter when there is one, reflection otherwise; "reflection" = always reflection, so domain setters never receive ciphertext during a flush. // Default: "auto"
 *     dbal_types?: bool|Param, // Register the encrypted DBAL types (encrypted_string, encrypted_text, encrypted_json, plus "<type>.<profile>" per profile) in DoctrineBundle. Encryption then happens in the DBAL conversion, without the listener or setters, and works with any mapping driver. Same ciphertext format and keys as #[Encrypted]. // Default: false
 *     connections?: Param|string|list<scalar|Param|null>,
 *     closed_manager_recovery?: bool|array{ // Reset, at the next main request, an entity manager left closed by a failed flush (its identity map still holds re-encrypted entities; needed when the kernel is not reset between requests, e.g. FrankenPHP worker mode). Only the managers the bundle encrypts for are reset, never another manager of the host.
 *         enabled?: bool|Param, // Default: true
 *         entity_managers?: Param|string|list<scalar|Param|null>,
 *     },
 *     default_profile?: scalar|Param|null, // Profile name to use when #[Encrypted] has no alias or uses "default". // Default: "default"
 *     on_decrypt_failure?: "keep"|"null"|"throw"|Param, // What an #[Encrypted] property gets when its stored value cannot be decrypted (wrong or rotated key, corrupted ciphertext): "keep" = the raw ciphertext (2.x default, callers may take it for the secret), "null" = null (recommended; the stored ciphertext is written back unchanged on flush), "throw" = DecryptionFailedException (recommended). A warning is logged for "keep" and "null". // Default: "keep"
 *     generate_missing_key?: bool|Param|null, // Whether the Halite / Defuse encryptors may create a missing key file on first use. null (default) = only when kernel.debug is true; outside debug a missing key file is an error (a wrong path or a lost volume would otherwise encrypt new data with a throwaway key). Create keys explicitly with doctrine:encrypt:generate-secret-key. // Default: null
 *     batch_size?: int|Param, // Default batch size for doctrine:decrypt:database and doctrine:encrypt:database (raw SQL). Overridable per run via the batchSize argument. // Default: 5
 *     secret_permissions?: array{ // Filesystem hardening for file-based keys: creates missing key directories with directory_mode and chmods key files to file_mode (console: every command; HTTP: at most once per http_check_interval seconds per worker).
 *         enabled?: bool|Param, // Default: true
 *         directory_mode?: int|Param, // Mode for key directories created by the bundle (octal string "0770" or "0700", or integer). Existing directories are not changed. // Default: 504
 *         file_mode?: int|Param, // Mode enforced on key files (octal string "0600" or integer). // Default: 384
 *         http_check_interval?: int|Param, // Seconds between HTTP checks per worker (0 = every main request). // Default: 60
 *     },
 *     profiles?: array<string, array{ // Default: []
 *         encryptor_class?: scalar|Param|null, // Default: "Halite"
 *         secret_directory_path?: scalar|Param|null, // Directory for the key file. Required unless secret_key_env_var is set. // Default: null
 *         secret_key_filename?: scalar|Param|null, // Optional custom key filename (e.g. .my_app.key). Only used when secret_directory_path is set. // Default: null
 *         secret_key_env_var?: scalar|Param|null, // Key content from env: use %env(APP_ENCRYPT_KEY)% so Symfony resolves it at config load and the bundle receives the value. When set, secret_directory_path and secret_key_filename are not allowed. // Default: null
 *         ...<string, mixed>
 *     }>,
 *     ...<string, mixed>
 * }
 * @psalm-type DebugConfig = array{
 *     max_items?: int|Param, // Max number of displayed items past the first level, -1 means no limit. // Default: 2500
 *     min_depth?: int|Param, // Minimum tree depth to clone all the items, 1 is default. // Default: 1
 *     max_string_length?: int|Param, // Max length of displayed strings, -1 means no limit. // Default: -1
 *     dump_destination?: scalar|Param|null, // A stream URL where dumps should be written to. // Default: null
 *     theme?: "dark"|"light"|Param, // Changes the color of the dump() output when rendered directly on the templating. "dark" (default) or "light". // Default: "dark"
 * }
 * @psalm-type WebProfilerConfig = array{
 *     toolbar?: bool|array{ // Profiler toolbar configuration
 *         enabled?: bool|Param, // Default: false
 *         ajax_replace?: bool|Param, // Replace toolbar on AJAX requests // Default: false
 *     },
 *     intercept_redirects?: bool|Param, // Default: false
 *     excluded_ajax_paths?: scalar|Param|null, // Default: "^/((index|app(_[\\w]+)?)\\.php/)?_wdt"
 * }
 * @psalm-type NowoHotReloadConfig = array{
 *     enabled?: bool|Param, // Master switch. When false, nothing is injected even if FRANKENPHP_HOT_RELOAD is set. // Default: true
 *     auto_inject?: bool|Param, // When true, HotReloadResponseSubscriber injects assets into HTML responses. // Default: true
 *     ignore_path_prefixes?: list<scalar|Param|null>,
 *     client_mode?: "cdn"|"visibility"|"shared_worker"|"always"|Param, // Browser Mercure client strategy: cdn (default ESM), visibility (SSE while tab visible), shared_worker (one SSE for all tabs), always (SSE per tab). // Default: "cdn"
 *     require_frankenphp_env?: bool|Param, // When true (default), inject only if FRANKENPHP_HOT_RELOAD is set or mercure_url is configured. // Default: true
 *     allow_production?: bool|Param, // When false (default), enabling this bundle in the prod environment raises InvalidConfigurationException. // Default: false
 *     mercure_url?: scalar|Param|null, // Optional Mercure hub URL. When null, uses $_SERVER['FRANKENPHP_HOT_RELOAD'] when present. // Default: null
 *     idiomorph?: bool|Param, // When true, include Idiomorph for DOM morphing instead of a full page reload. // Default: true
 *     idiomorph_script_url?: scalar|Param|null, // URL of the Idiomorph script (classic script tag). Prefer a version-pinned CDN URL. // Default: "https://cdn.jsdelivr.net/npm/idiomorph@0.7.4"
 *     hot_reload_script_url?: scalar|Param|null, // URL of the frankenphp-hot-reload ESM module. Prefer a version-pinned CDN URL. // Default: "https://cdn.jsdelivr.net/npm/frankenphp-hot-reload@1.0.1/+esm"
 *     preserve_selectors?: list<scalar|Param|null>,
 *     preserve_observe?: bool|Param, // When true, the preserve boot script also uses MutationObserver for late-injected toolbar nodes. // Default: true
 *     csp_nonce_request_attribute?: scalar|Param|null, // Request attribute name that holds the CSP nonce (e.g. "_csp_nonce"). Applied to the inline preserve boot script. // Default: null
 *     csp_augment_script_src?: bool|Param, // When true, append CDN hosts to an existing Content-Security-Policy script-src on the response after injection. // Default: true
 *     csp_script_src_hosts?: list<scalar|Param|null>,
 * }
 * @psalm-type NowoTwigInspectorConfig = array{
 *     enabled_extensions?: list<scalar|Param|null>,
 *     excluded_templates?: list<scalar|Param|null>,
 *     excluded_blocks?: list<scalar|Param|null>,
 *     enable_metrics?: bool|Param, // Enable collection of template usage metrics in DataCollector // Default: true
 *     inject_on_sub_requests?: bool|Param, // When true, inject comments also during sub-requests (e.g. when main content is rendered as fragment). Enable if all templates show "sub-request" and none get inspected. // Default: false
 *     cookie_name?: scalar|Param|null, // Name of the cookie used to enable/disable the inspector // Default: "twig_inspector_is_active"
 *     max_injection_depth?: int|Param, // Maximum nesting depth for comment injection (0 = unlimited). Reduces overhead on very deep template trees. // Default: 0
 *     excluded_templates_regex?: list<scalar|Param|null>,
 *     excluded_templates_prefixes?: list<scalar|Param|null>,
 *     excluded_blocks_regex?: list<scalar|Param|null>,
 *     overlay_theme?: scalar|Param|null, // Overlay theme: "light", "dark", or "auto" (follow system preference). // Default: "light"
 *     overlay_compact?: bool|Param, // Use compact tooltip style for the overlay. // Default: false
 *     reduced_motion?: bool|Param, // Respect reduced motion (accessibility). When true or system prefers-reduced-motion, animations are minimized. // Default: false
 *     keyboard_shortcut?: scalar|Param|null, // Keyboard shortcut to toggle inspector (e.g. "Ctrl+Shift+T"). Empty to disable. // Default: "Ctrl+Shift+T"
 * }
 * @psalm-type TwigExtraConfig = array{
 *     cache?: bool|array{
 *         enabled?: bool|Param, // Default: false
 *     },
 *     html?: bool|array{
 *         enabled?: bool|Param, // Default: false
 *     },
 *     markdown?: bool|array{
 *         enabled?: bool|Param, // Default: false
 *     },
 *     intl?: bool|array{
 *         enabled?: bool|Param, // Default: false
 *     },
 *     cssinliner?: bool|array{
 *         enabled?: bool|Param, // Default: false
 *     },
 *     inky?: bool|array{
 *         enabled?: bool|Param, // Default: false
 *     },
 *     string?: bool|array{
 *         enabled?: bool|Param, // Default: true
 *     },
 *     commonmark?: array{
 *         renderer?: array{ // Array of options for rendering HTML.
 *             block_separator?: scalar|Param|null,
 *             inner_separator?: scalar|Param|null,
 *             soft_break?: scalar|Param|null,
 *         },
 *         html_input?: "strip"|"allow"|"escape"|Param, // How to handle HTML input.
 *         allow_unsafe_links?: bool|Param, // Remove risky link and image URLs by setting this to false. // Default: true
 *         max_nesting_level?: int|Param, // The maximum nesting level for blocks. // Default: 9223372036854775807
 *         max_delimiters_per_line?: int|Param, // The maximum number of strong/emphasis delimiters per line. // Default: 9223372036854775807
 *         slug_normalizer?: array{ // Array of options for configuring how URL-safe slugs are created.
 *             instance?: mixed,
 *             max_length?: int|Param, // Default: 255
 *             unique?: mixed,
 *         },
 *         commonmark?: array{ // Array of options for configuring the CommonMark core extension.
 *             enable_em?: bool|Param, // Default: true
 *             enable_strong?: bool|Param, // Default: true
 *             use_asterisk?: bool|Param, // Default: true
 *             use_underscore?: bool|Param, // Default: true
 *             unordered_list_markers?: list<scalar|Param|null>,
 *         },
 *         ...<string, mixed>
 *     },
 * }
 * @psalm-type NowoFormKitConfig = array{
 *     type_map?: array<string, scalar|Param|null>,
 *     default_profile?: scalar|Param|null, // Name of the profile to use when no profile is specified (key in profiles) // Default: "default"
 *     convention_requires_translation?: bool|Param, // Global default for profiles.<name>.convention_requires_translation: only apply auto help / placeholder convention keys that exist in the translator catalogue. Requires a translator implementing TranslatorBagInterface (no effect otherwise). Default: false. // Default: false
 *     css_framework?: scalar|Param|null, // CSS framework for CssClassUtilities (column merge + class ordering): bootstrap, tailwind, foundation, none. // Default: "bootstrap"
 *     stateless_csrf?: array{ // Give selected (usually anonymous/public) form types a stateless CSRF token id so rendering them does not start a session. Off by default.
 *         enabled?: bool|Param, // Default: false
 *         token_id?: scalar|Param|null, // csrf_token_id default applied to the listed form types. Must be listed in framework.csrf_protection.stateless_token_ids. // Default: "submit"
 *         form_types?: list<scalar|Param|null>,
 *         register_stateless_token_id?: bool|Param, // Prepend token_id to framework.csrf_protection.stateless_token_ids (enables framework CSRF protection if it was not configured). // Default: true
 *     },
 *     accessibility?: array{ // Accessibility helpers applied by kit form type extensions.
 *         unlabelled_choice_aria_label?: bool|Param, // When true (default), non-expanded ChoiceType fields (and subtypes such as EntityType) rendered with label: false and no aria-label/aria-labelledby get aria-label from their translated help, else their placeholder. // Default: true
 *     },
 *     locale_tabs?: array{ // Locale tabs (2.13): the locale_tabs option, TranslatableMapType and the _locale_tabs include.
 *         locales?: list<scalar|Param|null>,
 *         default_locale?: scalar|Param|null, // Default language (marked and listed first). Null (default) = kernel.default_locale when it is one of the locales, else the first locale. // Default: null
 *         renderer?: "auto"|"ui_kit"|"native"|Param, // auto (default): the UiKit locale_tabs partial when nowo-tech/ui-kit-bundle 2.5+ is installed, else the neutral FormKit markup; native: always the neutral markup; ui_kit: same as auto (falls back to native without UiKit). // Default: "auto"
 *         form_theme?: bool|Param, // When true (default), registers @NowoFormKitBundle/form/locale_tabs_theme.html.twig in twig.form_themes (it only renders fields with locale_tabs). // Default: true
 *     },
 *     field_widgets?: array{ // Field widgets (2.22): the choice_cards (ChoiceType), switch (CheckboxType) and char_counter (TextType) options.
 *         form_theme?: bool|Param, // When true (default), registers @NowoFormKitBundle/form/field_widgets_theme.html.twig in twig.form_themes (it only renders fields with choice_cards, switch or char_counter). // Default: true
 *     },
 *     twig?: array{ // Twig helpers shipped by the bundle (registered only when twig/twig is installed).
 *         csrf_action_form?: bool|Param, // When true (default), registers the csrf_action_form() Twig function (CSRF-only single-action POST forms). Set false if the host keeps its own function with that name. // Default: true
 *     },
 *     profiles?: array<string, array{ // Default: []
 *         alias?: scalar|Param|null, // Alias for this profile (e.g. for reference in form types)
 *         translation_domain?: scalar|Param|null, // Default: "messages"
 *         auto_placeholder?: bool|Param, // When true (default), unset placeholders become {form}.{field}.placeholder translation keys. Set false for kits that only set explicit labels. // Default: true
 *         auto_help?: bool|Param, // When true (default), unset help becomes {form}.{field}.help translation keys. Set false to avoid raw missing-help keys in the UI. // Default: true
 *         convention_requires_translation?: bool|Param|null, // When true, the auto help / placeholder convention keys are only applied if the key exists in the translator catalogue (current locale + fallbacks) for the field's translation domain. Never affects labels. Null (default) inherits the root convention_requires_translation. // Default: null
 *         required_label_suffix?: scalar|Param|null, // Appended to the label when the field is required (e.g. " *"). Empty or null to disable. // Default: null
 *         help_attr?: array<string, scalar|Param|null>,
 *         css_framework?: scalar|Param|null, // CSS framework of this profile's form theme chrome (2.13; static_blocks checkbox_row): bootstrap adds form-check / form-check-input / form-check-label and the mb-3 row fallback. Null (default) = the root css_framework. // Default: null
 *         help_modal?: array{ // Default help modal configuration (used when the field option "help_modal" is enabled).
 *             framework?: scalar|Param|null, // Modal framework to use when opening from frontend. // Default: "bootstrap5"
 *             icon_html?: scalar|Param|null, // HTML snippet inserted next to the label to trigger the help modal (fallback when ux_icon is not used or UX Icons is unavailable). // Default: "<span class=\"nowo-help-modal-icon\" aria-hidden=\"true\">?</span>"
 *             ux_icon?: scalar|Param|null, // Optional. Symfony UX Icons name (e.g. lucide:circle-help). Requires symfony/ux-icons; when set and IconRendererInterface is available, overrides icon_html. // Default: null
 *             ux_icon_attributes?: array<string, scalar|Param|null>,
 *             trigger_class?: scalar|Param|null, // CSS classes for the clickable trigger wrapper (after label text and required suffix). Default: circle button style. // Default: "nowo-help-modal-trigger nowo-help-modal-trigger--circle"
 *         },
 *         defaults?: array{
 *             attr?: array<string, scalar|Param|null>,
 *             row_attr?: array<string, scalar|Param|null>,
 *             help_attr?: array<string, scalar|Param|null>,
 *             label?: scalar|Param|null, // Default label for every field (e.g. false to suppress). When set, overrides the {form}.{field}.label convention.
 *             placeholder?: scalar|Param|null, // Default placeholder for every field (e.g. false to suppress). When set, overrides auto_placeholder convention.
 *             help?: scalar|Param|null, // Default help for every field (e.g. false to suppress). When set, overrides auto_help convention.
 *             required?: bool|Param, // Default required flag for every field. Overridable via field_types, by_form, or PHP options.
 *         },
 *         field_types?: array<string, array{ // Default: []
 *             attr?: array<string, scalar|Param|null>,
 *             row_attr?: array<string, scalar|Param|null>,
 *             help_attr?: array<string, scalar|Param|null>,
 *             label?: scalar|Param|null,
 *             placeholder?: scalar|Param|null,
 *             help?: scalar|Param|null,
 *             required?: bool|Param, // Default required flag for this field type / field. Overridable by later cascade layers or PHP options.
 *             translation_domain?: scalar|Param|null,
 *             constraints?: list<mixed>,
 *         }>,
 *         constraint_message_convention?: bool|Param, // When true, constraints without an explicit "message" get key {form_snake}.{field_snake}.constraints.{ConstraintName} (put translations in the validators catalog). Default: false. // Default: false
 *         by_form?: array<string, array{ // Default: []
 *             defaults?: array{
 *                 attr?: array<string, scalar|Param|null>,
 *                 row_attr?: array<string, scalar|Param|null>,
 *                 help_attr?: array<string, scalar|Param|null>,
 *                 label?: scalar|Param|null, // Default label for every field (e.g. false to suppress). When set, overrides the {form}.{field}.label convention.
 *                 placeholder?: scalar|Param|null, // Default placeholder for every field (e.g. false to suppress). When set, overrides auto_placeholder convention.
 *                 help?: scalar|Param|null, // Default help for every field (e.g. false to suppress). When set, overrides auto_help convention.
 *                 required?: bool|Param, // Default required flag for every field. Overridable via field_types, by_form, or PHP options.
 *             },
 *             fields?: array<string, array{ // Default: []
 *                 attr?: array<string, scalar|Param|null>,
 *                 row_attr?: array<string, scalar|Param|null>,
 *                 help_attr?: array<string, scalar|Param|null>,
 *                 label?: scalar|Param|null,
 *                 placeholder?: scalar|Param|null,
 *                 help?: scalar|Param|null,
 *                 required?: bool|Param, // Default required flag for this field type / field. Overridable by later cascade layers or PHP options.
 *                 translation_domain?: scalar|Param|null,
 *                 constraints?: list<mixed>,
 *             }>,
 *         }>,
 *     }>,
 *     translation_domain?: scalar|Param|null, // (Legacy) Used when profiles is not set // Default: "messages"
 *     required_label_suffix?: scalar|Param|null, // (Legacy) Suffix for required field labels when profiles is not set // Default: null
 *     help_modal?: array{ // (Legacy) Default help modal configuration when profiles is not used.
 *         framework?: scalar|Param|null, // Default: "bootstrap5"
 *         icon_html?: scalar|Param|null, // Default: "<span class=\"nowo-help-modal-icon\" aria-hidden=\"true\">?</span>"
 *         ux_icon?: scalar|Param|null, // Default: null
 *         ux_icon_attributes?: array<string, scalar|Param|null>,
 *         trigger_class?: scalar|Param|null, // Default: "nowo-help-modal-trigger nowo-help-modal-trigger--circle"
 *     },
 *     defaults?: array{
 *         attr?: array<string, scalar|Param|null>,
 *         row_attr?: array<string, scalar|Param|null>,
 *         help_attr?: array<string, scalar|Param|null>,
 *         label?: scalar|Param|null, // Default label for every field (e.g. false to suppress). When set, overrides the {form}.{field}.label convention.
 *         placeholder?: scalar|Param|null, // Default placeholder for every field (e.g. false to suppress). When set, overrides auto_placeholder convention.
 *         help?: scalar|Param|null, // Default help for every field (e.g. false to suppress). When set, overrides auto_help convention.
 *         required?: bool|Param, // Default required flag for every field. Overridable via field_types, by_form, or PHP options.
 *     },
 *     field_types?: array<string, array{ // Default: []
 *         attr?: array<string, scalar|Param|null>,
 *         row_attr?: array<string, scalar|Param|null>,
 *         help_attr?: array<string, scalar|Param|null>,
 *         label?: scalar|Param|null,
 *         placeholder?: scalar|Param|null,
 *         help?: scalar|Param|null,
 *         required?: bool|Param, // Default required flag for this field type / field. Overridable by later cascade layers or PHP options.
 *         translation_domain?: scalar|Param|null,
 *         constraints?: list<mixed>,
 *     }>,
 *     constraint_message_convention?: bool|Param, // (Legacy) Used when profiles is not set // Default: false
 *     by_form?: array<string, array{ // Default: []
 *         defaults?: array{
 *             attr?: array<string, scalar|Param|null>,
 *             row_attr?: array<string, scalar|Param|null>,
 *             help_attr?: array<string, scalar|Param|null>,
 *             label?: scalar|Param|null, // Default label for every field (e.g. false to suppress). When set, overrides the {form}.{field}.label convention.
 *             placeholder?: scalar|Param|null, // Default placeholder for every field (e.g. false to suppress). When set, overrides auto_placeholder convention.
 *             help?: scalar|Param|null, // Default help for every field (e.g. false to suppress). When set, overrides auto_help convention.
 *             required?: bool|Param, // Default required flag for every field. Overridable via field_types, by_form, or PHP options.
 *         },
 *         fields?: array<string, array{ // Default: []
 *             attr?: array<string, scalar|Param|null>,
 *             row_attr?: array<string, scalar|Param|null>,
 *             help_attr?: array<string, scalar|Param|null>,
 *             label?: scalar|Param|null,
 *             placeholder?: scalar|Param|null,
 *             help?: scalar|Param|null,
 *             required?: bool|Param, // Default required flag for this field type / field. Overridable by later cascade layers or PHP options.
 *             translation_domain?: scalar|Param|null,
 *             constraints?: list<mixed>,
 *         }>,
 *     }>,
 *     ...<string, mixed>
 * }
 * @psalm-type NowoUiKitConfig = array{
 *     css_framework?: "bootstrap"|"bootstrap5"|"bootstrap4"|"tailwind"|"foundation"|"custom"|"tabler"|"none"|Param, // Host CSS stack: bootstrap5|bootstrap4|tailwind|foundation|custom|none|tabler (bootstrap alias → bootstrap5). // Default: "bootstrap5"
 *     icon_set?: "bootstrap-icons"|"tabler-icons"|"ux_icon"|"svg_inline"|"none"|Param, // Icon rendering: bootstrap-icons|tabler-icons|ux_icon|svg_inline|none. // Default: "bootstrap-icons"
 *     row_actions_display?: "icon"|"text"|"icon_text"|Param, // Table/list row actions: icon (glyph only) | text (label only) | icon_text (both). // Default: "icon"
 *     row_actions_confirm?: "inline"|"native"|"dialog"|Param, // confirm_message of 1.x row_actions POST hashes (2.22, REQ-UI-003): inline = onsubmit="return confirm(…)" (default, unchanged markup); native | dialog = no inline handler, the confirm-submit Stimulus peer asks (window.confirm or the kit <dialog>). native / dialog need the confirm-submit peer registered. // Default: "inline"
 *     action_icons?: array<string, scalar|Param|null>,
 *     action_variants?: array<string, scalar|Param|null>,
 *     panel_path_rewrites?: array<string, scalar|Param|null>,
 *     release_check?: array{ // Optional GitHub release-version checker (footer dialog + JSON endpoint). Off by default.
 *         enabled?: bool|Param, // Register the checker service + controller. Also import @NowoUiKitBundle/Resources/config/routes/release_check.yaml. Since 2.33 also a container parameter or "%env(bool:…)%" (false at runtime = the check is skipped and the endpoint answers 404). // Default: false
 *         github_repo?: scalar|Param|null, // Public GitHub repository "owner/name" whose releases are compared (e.g. nowo-tech/my-app). // Default: ""
 *         current_version?: scalar|Param|null, // Installed version label, clean semver x.y.z with optional "v" (e.g. "%app.version%"). Non-semver values skip the check. // Default: ""
 *         cache_ttl?: int|Param, // Seconds to cache the GitHub answer (cache.app). Default 12h. // Default: 43200
 *         default_branch?: scalar|Param|null, // Branch used as compare target when the installed version is already the latest. // Default: "main"
 *         user_agent?: scalar|Param|null, // User-Agent sent to api.github.com. // Default: "nowo-ui-kit-release-check"
 *         timeout?: float|Param, // Idle timeout in seconds of the GitHub "releases/latest" request (HttpClient "timeout"). Keep it below PHP max_execution_time (REQ-RUNTIME-001). // Default: 5.0
 *         releases_timeout?: float|Param, // Idle timeout in seconds of the GitHub "releases" listing used to count versions behind. Keep it below PHP max_execution_time (REQ-RUNTIME-001). // Default: 8.0
 *     },
 *     route_links?: array{ // Route (or route prefix) → link map read by nowo_ui_route_link(), e.g. for the page-edge "manual" tab (partials/_edge_tab.html.twig). A prefix matches the route itself or any route starting with "<prefix>_"; the longest prefix wins.
 *         base_url?: scalar|Param|null, // Prepended to every relative path (paths starting with "/", "//", "http://" or "https://" are used as is). // Default: ""
 *         default_testid?: scalar|Param|null, // testid returned for entries without their own. // Default: null
 *         routes?: array<string, Param|string|array{ // Default: []
 *             path?: scalar|Param|null,
 *             testid?: scalar|Param|null, // Default: null
 *         }>,
 *     },
 *     base_stylesheet?: bool|Param, // 2.51: link UiKit's opt-in default theme (css/nowo-ui-base.css) before nowo-ui.css in the document / error layouts: body font stack, page gutters and masthead height for hosts that bring no CSS framework and no theme stylesheet. Its rules nest in `@layer theme.nowo-ui-base`, below `base`, so a framework, host utilities and unlayered host CSS still win. Leave it false when the host already has a framework or a theme file. // Default: false
 *     font_preload?: array{ // 2.21: <link rel="preload" as="font" crossorigin> tags printed in the <head> of the document and error layouts (Twig nowo_ui_font_preload()) for the fonts painted above the fold. Empty = no tags.
 *         fonts?: list<scalar|Param|null>,
 *         manifest?: array{ // Hashed builds: pick the fonts from a Vite manifest (.vite/manifest.json). A missing manifest (dev server) prints nothing.
 *             path?: scalar|Param|null, // Absolute path of the manifest, e.g. "%kernel.project_dir%/public/build/.vite/manifest.json"; null = off. // Default: null
 *             entry?: scalar|Param|null, // Manifest entry whose "assets" list holds the fonts. // Default: "assets/app.js"
 *             prefixes?: list<scalar|Param|null>,
 *             base?: scalar|Param|null, // Public URL prefix of the manifest assets. // Default: "/build/"
 *         },
 *     },
 *     appearance?: array{ // Optional site appearance SPI (presets, --nowo-ui-* token emitter, Twig functions). Off by default.
 *         enabled?: bool|Param, // Default: false
 *         provider?: scalar|Param|null, // Service id implementing AppearanceProviderInterface (host storage). Null = the config defaults below (or, 2.40, the brand provider: see brand). // Default: null
 *         brand?: "auto"|"off"|Param, // 2.40 (spec 220): auto = with provider null and a nowo-tech/brand-contracts BrandProviderInterface service, the brand drives the name, eyebrow, colours and logos (corner, border and footer stay from defaults). off = always the defaults. No brand service = no change. // Default: "auto"
 *         head_links?: bool|Param, // 2.40: the kit layouts (document, error) print nowo_ui_appearance_head() in <head>: favicon / apple-touch links of the appearance logos and meta theme-color (paper colour per colour scheme). Off = the 2.39 <head>. // Default: false
 *         defaults?: array{
 *             brand_name?: scalar|Param|null, // Default: ""
 *             brand_eyebrow?: scalar|Param|null, // Default: ""
 *             preset?: scalar|Param|null, // Preset whose light colours are the default. // Default: "slate"
 *             preset_dark?: scalar|Param|null, // Preset whose dark colours are the default. // Default: "midnight"
 *             corner_style?: "sharp"|"soft"|"rounded"|Param, // Default: "soft"
 *             border_strength?: "subtle"|"medium"|"strong"|Param, // Default: "medium"
 *             footer_fixed?: bool|Param, // Default: false
 *         },
 *         presets?: array<string, array{ // Default: []
 *             mode?: "light"|"dark"|Param, // Default: "light"
 *             light?: array{
 *                 accent?: scalar|Param|null,
 *                 accent_deep?: scalar|Param|null,
 *                 danger?: scalar|Param|null,
 *                 warn?: scalar|Param|null,
 *                 paper?: scalar|Param|null,
 *                 ink?: scalar|Param|null,
 *                 surface?: scalar|Param|null,
 *             },
 *             dark?: array{
 *                 accent?: scalar|Param|null,
 *                 accent_deep?: scalar|Param|null,
 *                 danger?: scalar|Param|null,
 *                 warn?: scalar|Param|null,
 *                 paper?: scalar|Param|null,
 *                 ink?: scalar|Param|null,
 *                 surface?: scalar|Param|null,
 *             },
 *         }>,
 *         builtin_presets?: list<scalar|Param|null>,
 *         kit_tokens?: bool|Param, // Emit the --nowo-ui-* tokens. False = only the token_aliases are written. // Default: true
 *         token_aliases?: array<string, list<scalar|Param|null>>,
 *         selectors?: array{ // Selectors of the three emitted blocks.
 *             root?: scalar|Param|null, // Default: ":root"
 *             light?: scalar|Param|null, // Default: ":root, [data-theme=\"light\"]"
 *             dark?: scalar|Param|null, // Default: "[data-theme=\"dark\"]"
 *         },
 *         shadow_rgb_fallback?: scalar|Param|null, // --nowo-ui-shadow-rgb when the light ink colour is not hex ("r g b"). // Default: "15 23 42"
 *     },
 *     layouts?: array{ // Layouts the page scaffolds extend (Twig global nowo_ui_layout). Host layouts or one-file bridges must render the canonical nowo_ui_* blocks.
 *         admin?: scalar|Param|null, // Default: "@NowoUiKit/layout/admin.html.twig"
 *         guest?: scalar|Param|null, // Default: "@NowoUiKit/layout/guest.html.twig"
 *     },
 *     breadcrumbs?: array{ // Page header breadcrumbs: BreadcrumbKit (breadcrumb_render) when installed and enabled here, else the plain trail from PageHeader::breadcrumbs.
 *         breadcrumb_kit?: bool|Param, // Use BreadcrumbKit when its breadcrumb_render() Twig function exists. // Default: true
 *         collection?: scalar|Param|null, // BreadcrumbKit collection code (null = its default collection). PageHeader::breadcrumbCollection overrides it. // Default: null
 *         enabled?: bool|Param, // 2.3.1: false drops the breadcrumb trail (BreadcrumbKit and PageHeader::breadcrumbs) from every page header, e.g. when the host layout renders its own. // Default: true
 *     },
 *     header?: array{ // 2.27 compact header: the default page-header presentation (a request attribute _nowo_ui_header_presentation overrides it per request) and where PageHeader::actions go in the compact presentation.
 *         presentation?: "standard"|"compact"|"inline"|"no_intro"|"no_title"|"no_header"|Param, // standard (default) | compact | inline | no_intro | no_title | no_header: in compact the page header keeps a visually hidden h1 and leaves its intro, breadcrumbs and back link to the top bar (header_context); 2.39: inline puts the intro on the title row, no_intro drops the intro, no_title keeps the h1 visually hidden, no_header does both. // Default: "standard"
 *         compact_actions?: "page"|"bar"|Param, // page (default): header.actions stay in the page header; bar: in the compact presentation the page header leaves them out (the host shows them in the top bar through the page_actions partial). extra_actions always stay. // Default: "page"
 *     },
 *     auth_panel?: array{ // 2.44: the auth scaffold (page/auth.html.twig) panel (Twig global nowo_ui_kit_auth_panel).
 *         brand?: "auto"|"none"|Param, // auto (default, the 2.43 markup): the panel brand region prints the appearance logo lockup, else AuthPage::brand as text; none: the panel prints no brand region (a host guest layout that prints its own brand). A render variable nowo_ui_auth_brand (auto | none) wins over it. // Default: "auto"
 *     },
 *     remote_dialog?: array{ // 2.28 remote form dialog (X-Nowo-Dialog requests).
 *         convert_redirects?: bool|Param, // Turn a RedirectResponse answered to a dialog request (X-Nowo-Dialog: 1, main request) into 204 + X-Nowo-Redirect when its Location is a same-origin path, so redirectToRoute() controllers work in the dialog without code changes. // Default: false
 *         step_routes?: list<scalar|Param|null>,
 *         request_header?: scalar|Param|null, // 2.33: request header the dialog sends with "1" (both drivers, RemoteDialogResponder, the redirect listener, nowo_ui_dialog_request()). // Default: "X-Nowo-Dialog"
 *         redirect_header?: scalar|Param|null, // 2.33: response header of the success answer (204 + <path>: the dialog closes and the page goes there). // Default: "X-Nowo-Redirect"
 *         location_header?: scalar|Param|null, // 2.33: response header of the step answer (204 + <path>: the dialog stays open and loads the next step). // Default: "X-Nowo-Dialog-Location"
 *     },
 *     theme?: array{ // Global theme (REQ-UI-003): semantic slot classes / attributes, partial overrides and --nowo-ui-* tokens, configured once for every bundle.
 *         framework_defaults?: bool|Param, // false = slots print the semantic nowo-ui-* class and the host classes only (no UiKit framework defaults such as btn / badge / card). Per slot: classes.<slot>: {value: …, framework_defaults: false}. // Default: true
 *         classes?: array<string, mixed>,
 *         attributes?: array<string, array<string, mixed>>,
 *         partials?: array<string, scalar|Param|null>,
 *         tokens?: array<string, mixed>,
 *         tokens_dark?: array<string, mixed>,
 *         confirm_message_in_header?: bool|Param, // Confirm dialogs and the full-page confirm render the message inside the header, under the title (hosts whose dialog header is title + message). View\Confirm::messageInHeader wins when set. // Default: false
 *         popover_menus?: bool|Param, // 2.4: menu / kebab / row-action menus render their panel as a native [popover] (browser top layer: never clipped by table scrollers or cards; nowo-ui-popover.js places it). The partial option `popover` wins. // Default: false
 *         form_errors_after_widget?: bool|Param, // 2.10: the UiKit form theme (form/nowo_ui_layout.html.twig) prints a field's errors after its widget (and before the help), instead of Symfony's order (label, errors, widget, help). // Default: false
 *         menu_semantics?: null|"menu"|"disclosure"|Param, // 2.28: ARIA of popover menus with items. null (default) = as 2.27; menu = APG menu button (role="menu" / menuitem, arrow keys in nowo-ui-popover.js); disclosure = no aria-haspopup. The menu partial option `menu_semantics` wins. // Default: null
 *         dialog?: array{ // 2.31: kit dialog options (docs/THEMING.md#dialog-sizes-229).
 *             height?: "content"|"size"|Param, // 2.31: content (default, dialog rule v2) = a dialog is as tall as its content, capped at min(the size's height token, 100dvh - 2rem), only the body scrolls; size = every dialog with a field, list, table or fold has the size's height (2.29 / 2.30), printed as --nowo-ui-dialog-fill: 1 by nowo_ui_theme_css(). // Default: "content"
 *         },
 *         bundles?: array<string, array{ // Default: []
 *             framework_defaults?: bool|Param, // Override of theme.framework_defaults for this bundle (unset = inherit).
 *             classes?: array<string, mixed>,
 *             attributes?: array<string, array<string, mixed>>,
 *             partials?: array<string, scalar|Param|null>,
 *             tokens?: array<string, mixed>,
 *             tokens_dark?: array<string, mixed>,
 *             pages?: array<string, array{ // Default: []
 *                 framework_defaults?: bool|Param, // Override of theme.framework_defaults for this page type (unset = inherit).
 *                 classes?: array<string, mixed>,
 *                 attributes?: array<string, array<string, mixed>>,
 *             }>,
 *         }>,
 *         pages?: array<string, array{ // Default: []
 *             framework_defaults?: bool|Param, // Override of theme.framework_defaults for this page type (unset = inherit).
 *             classes?: array<string, mixed>,
 *             attributes?: array<string, array<string, mixed>>,
 *             header_actions_in_filters?: bool|Param, // list only: render the page-header actions at the end of the filter row instead of the page header (ListPage::headerActionsInFilters wins when set; unset = false).
 *             filters?: array{ // list only (2.30): host options of the list scaffold filters (page/_list_filters.html.twig). Unset = the 2.29 output.
 *                 display?: null|"text"|"icon"|"icon_text"|Param, // Default display of the apply / clear / extra filter buttons (ListPage::filtersDisplay and Action::display win; set, the plain clear link follows it too). null = the 2.29 behaviour. // Default: null
 *                 submit_icon?: scalar|Param|null, // Icon of the apply button when it shows one (components/_icon.html.twig name; null = filter). ListPage::filtersSubmit->icon wins. // Default: null
 *                 clear_icon?: scalar|Param|null, // Icon of the plain clear link (ListPage::filtersClearHref) when it shows one (null = filter_off). // Default: null
 *                 submit_class?: scalar|Param|null, // Host classes of the apply button, replacing its btn_<variant> slot classes (nowo-ui-filters__submit and, icon-only, the btn_icon slot stay). null = the slot classes. // Default: null
 *                 clear_class?: scalar|Param|null, // Host classes of the clear button (plain link or ListPage::filtersClear Action), replacing its btn_<variant> slot classes. null = the slot classes. // Default: null
 *                 grid?: scalar|Param|null, // Grid tracks of the filter fields: auto-fit (default: a lone filter stretches across the card), auto-fill (empty tracks keep their width) or a grid-template-columns track list such as "repeat(auto-fill, minmax(min(100%, 13rem), 1fr))" (printed as the --nowo-ui-filters-grid-columns token by nowo_ui_tokens(); no inline style). // Default: "auto-fit"
 *                 fold?: array{ // Fold toggle of the filters card (partials/_filters_collapse.html.twig, Stimulus peer filters-collapse). ListPage::filtersFold wins.
 *                     enabled?: bool|Param, // Render the fold toggle (the card renders open unless the cookie remembers it folded). // Default: false
 *                     cookie?: scalar|Param|null, // Cookie remembering the folded pages (comma-separated page keys; SameSite=Lax, no secrets). // Default: "nowo_ui_filters_collapsed"
 *                     cookie_path?: scalar|Param|null, // Path of that cookie. // Default: "/"
 *                     icons?: array{
 *                         expanded?: scalar|Param|null, // Icon while the card is open. // Default: "chevron_up"
 *                         collapsed?: scalar|Param|null, // Icon while the card is folded. // Default: "chevron_down"
 *                     },
 *                     testids?: bool|Param, // data-testid filters-collapse-toggle / filters-collapse-summary. // Default: false
 *                     morph?: bool|Param, // Add the <morph-icon> of assets/morphicons to the toggle. // Default: false
 *                 },
 *             },
 *         }>,
 *     },
 *     error_pages?: array{ // Kit error pages (2.6): templates/bundles/TwigBundle/Exception/error.html.twig = {% extends '@NowoUiKit/error/status.html.twig' %}.
 *         layout_template?: scalar|Param|null, // Document the status template extends: the kit layout, or a host template extending it (own <head> assets, brand, chat dock…). // Default: "@NowoUiKit/error/layout.html.twig"
 *         translation_domain?: scalar|Param|null, // Domain of the page texts (e.g. messages). Keys missing there fall back to UiKit's own NowoUiKitBundle error.* texts. // Default: "NowoUiKitBundle"
 *         translation_prefix?: scalar|Param|null, // Key prefix: <prefix>.<code>.title|lead|hint|image_alt, <prefix>.generic.*, <prefix>.home|retry|back|retry_after|auto_retry|skip_to_content. // Default: "error"
 *         home_path?: scalar|Param|null, // Href of the "Back to home" button. // Default: "/"
 *         illustrations?: array<string, Param|string|array{ // Default: []
 *             path?: scalar|Param|null,
 *             width?: int|Param, // Default: null
 *             height?: int|Param, // Default: null
 *             webp?: bool|Param|null, // Default: null
 *             extension?: scalar|Param|null, // Default: null
 *         }>,
 *         illustration_width?: int|Param, // Default intrinsic width of the illustrations. // Default: 1024
 *         illustration_height?: int|Param, // Default intrinsic height of the illustrations. // Default: 683
 *         illustration_provider?: scalar|Param|null, // 2.47: service id implementing Nowo\UiKitBundle\View\ErrorIllustrationProviderInterface, asked first per status code (null answer, any exception or an invalid illustration → the illustrations map). Must be fast and must not need the database. Null = the map only. // Default: null
 *         illustration_provider_budget_ms?: int|Param, // 2.47: when > 0, a provider call slower than this many milliseconds makes the rest of the same request skip the provider (logged once). 0 = no budget. // Default: 0
 *         webp?: bool|Param, // Illustrations ship a .webp next to the fallback image (<source type="image/webp">). // Default: true
 *         retry_statuses?: list<int|Param>,
 *         back_link_statuses?: list<int|Param>,
 *         auto_retry_seconds?: int|Param, // A 5xx on GET reloads itself after this many seconds (REQ-ERROR-001 item 10; 0 = never). Never on POST. // Default: 600
 *         theme_storage?: scalar|Param|null, // localStorage key of the theme (data-nowo-ui-theme-storage on <html> and the toggle), e.g. the host's beacon-theme. // Default: "nowo-ui-theme"
 *         theme_toggle?: bool|Param, // Render the light / dark toggle in the top controls. // Default: true
 *         html_attributes?: array<string, mixed>,
 *     },
 *     shell?: array{ // Signed-in app shell (2.6, @NowoUiKit/shell/_open|_close|_shell|_burger.html.twig; Twig global nowo_ui_kit_shell).
 *         kit_styles?: bool|Param, // Add the kit shell classes (nowo-ui-shell, nowo-ui-header, nowo-ui-aside…) painted by nowo-ui.css and driven by nowo-ui-shell.js. false: only the app_* slot classes (host shell CSS / JS). // Default: true
 *         sidebar_cookie?: scalar|Param|null, // Cookie holding the desktop aside mode (e.g. beacon_sidebar), read server-side so the first paint is already collapsed / rail; null = expanded unless the template passes collapsed. // Default: null
 *         sidebar_hidden_value?: scalar|Param|null, // Cookie value of the hidden aside. // Default: "collapsed"
 *         sidebar_rail_value?: scalar|Param|null, // Cookie value of the icons-only rail. // Default: "rail"
 *         sidebar_storage?: scalar|Param|null, // localStorage key of the aside mode (data-nowo-ui-sidebar-storage on the shell root; kit_styles + rail only). // Default: null
 *         sidebar_layout?: "below-header"|"full-height"|Param, // 2.32: below-header (default, the 2.31 markup byte for byte) | full-height: on desktop the aside runs from the top edge with the burger and the brand in its head (aside_brand_html / brand_icon_html + brand_name / brand_html), the header and the footer start at its right edge. The app_shell_open option sidebar_layout, then the request attribute _nowo_ui_sidebar_layout, win over it. // Default: "below-header"
 *         sidebar_style?: "classic"|"inset"|Param, // 2.52: classic (default, the 2.51 markup byte for byte) | inset: on desktop the header, the aside and the footer share one shell surface with no border between them, and the content is an inset panel with rounded corners (tokens --nowo-ui-shell-inset-gap / -radius / -surface / -panel-surface). Phones are unchanged. The app_shell_open option sidebar_style, then the request attribute _nowo_ui_sidebar_style, win over it. // Default: "classic"
 *         brand?: array{ // 2.32: the shell brand from config (no template override): the aside head of the full-height layout, and the header brand when a page passes neither brand_html nor brand. The app_shell_open options brand_name / brand_href / brand_icon_src / brand_icon_html / aside_brand_html win.
 *             name?: scalar|Param|null, // Brand name (text); the icons-only rail hides it and keeps it as the link name. // Default: null
 *             href?: scalar|Param|null, // Brand link: relative, http, https or mailto (validated when the container compiles; javascript:, data: and other schemes are refused). // Default: null
 *             icon_src?: scalar|Param|null, // Brand icon image URL (e.g. /images/logo.svg; prefer same-origin or https), rendered as <img> in every aside mode; without it the name's initial. Relative, http or https only (validated when the container compiles). // Default: null
 *         },
 *         hover_expand?: bool|Param, // 2.32: with kit_styles and rail_mode, the icons-only rail widens over the page while the pointer or the keyboard focus is on it (data-nowo-ui-sidebar-hover-expand on the shell root, is-aside-peek set by nowo-ui-shell.js). The app_shell_open option hover_expand wins. // Default: false
 *     },
 * }
 * @psalm-type ConfigType = array{
 *     imports?: ImportsConfig,
 *     parameters?: ParametersConfig,
 *     services?: ServicesConfig,
 *     framework?: FrameworkConfig,
 *     doctrine?: DoctrineConfig,
 *     twig?: TwigConfig,
 *     nowo_doctrine_encrypt?: NowoDoctrineEncryptConfig,
 *     twig_extra?: TwigExtraConfig,
 *     nowo_form_kit?: NowoFormKitConfig,
 *     nowo_ui_kit?: NowoUiKitConfig,
 *     "when@dev"?: array{
 *         imports?: ImportsConfig,
 *         parameters?: ParametersConfig,
 *         services?: ServicesConfig,
 *         framework?: FrameworkConfig,
 *         doctrine?: DoctrineConfig,
 *         twig?: TwigConfig,
 *         nowo_doctrine_encrypt?: NowoDoctrineEncryptConfig,
 *         debug?: DebugConfig,
 *         web_profiler?: WebProfilerConfig,
 *         nowo_hot_reload?: NowoHotReloadConfig,
 *         nowo_twig_inspector?: NowoTwigInspectorConfig,
 *         twig_extra?: TwigExtraConfig,
 *         nowo_form_kit?: NowoFormKitConfig,
 *         nowo_ui_kit?: NowoUiKitConfig,
 *     },
 *     "when@prod"?: array{
 *         imports?: ImportsConfig,
 *         parameters?: ParametersConfig,
 *         services?: ServicesConfig,
 *         framework?: FrameworkConfig,
 *         doctrine?: DoctrineConfig,
 *         twig?: TwigConfig,
 *         nowo_doctrine_encrypt?: NowoDoctrineEncryptConfig,
 *         twig_extra?: TwigExtraConfig,
 *         nowo_form_kit?: NowoFormKitConfig,
 *         nowo_ui_kit?: NowoUiKitConfig,
 *     },
 *     "when@test"?: array{
 *         imports?: ImportsConfig,
 *         parameters?: ParametersConfig,
 *         services?: ServicesConfig,
 *         framework?: FrameworkConfig,
 *         doctrine?: DoctrineConfig,
 *         twig?: TwigConfig,
 *         nowo_doctrine_encrypt?: NowoDoctrineEncryptConfig,
 *         web_profiler?: WebProfilerConfig,
 *         nowo_hot_reload?: NowoHotReloadConfig,
 *         nowo_twig_inspector?: NowoTwigInspectorConfig,
 *         twig_extra?: TwigExtraConfig,
 *         nowo_form_kit?: NowoFormKitConfig,
 *         nowo_ui_kit?: NowoUiKitConfig,
 *     },
 *     ...<string, ExtensionType|array{ // extra keys must follow the when@%env% pattern or match an extension alias
 *         imports?: ImportsConfig,
 *         parameters?: ParametersConfig,
 *         services?: ServicesConfig,
 *         ...<string, ExtensionType>,
 *     }>
 * }
 */
final class App
{
    /**
     * @param ConfigType $config
     *
     * @psalm-return ConfigType
     */
    public static function config(array $config): array
    {
        /** @var ConfigType $config */
        $config = AppReference::config($config);

        return $config;
    }
}

namespace Symfony\Component\Routing\Loader\Configurator;

/**
 * This class provides array-shapes for configuring the routes of an application.
 *
 * Example:
 *
 *     ```php
 *     // config/routes.php
 *     namespace Symfony\Component\Routing\Loader\Configurator;
 *
 *     return Routes::config([
 *         'controllers' => [
 *             'resource' => 'routing.controllers',
 *         ],
 *     ]);
 *     ```
 *
 * @psalm-type RouteConfig = array{
 *     path: string|array<string,string>,
 *     controller?: string,
 *     methods?: string|list<string>,
 *     requirements?: array<string,string>,
 *     defaults?: array<string,mixed>,
 *     options?: array<string,mixed>,
 *     host?: string|array<string,string>,
 *     schemes?: string|list<string>,
 *     condition?: string,
 *     locale?: string,
 *     format?: string,
 *     utf8?: bool,
 *     stateless?: bool,
 * }
 * @psalm-type ImportConfig = array{
 *     resource: string,
 *     type?: string,
 *     exclude?: string|list<string>,
 *     prefix?: string|array<string,string>,
 *     name_prefix?: string,
 *     trailing_slash_on_root?: bool,
 *     controller?: string,
 *     methods?: string|list<string>,
 *     requirements?: array<string,string>,
 *     defaults?: array<string,mixed>,
 *     options?: array<string,mixed>,
 *     host?: string|array<string,string>,
 *     schemes?: string|list<string>,
 *     condition?: string,
 *     locale?: string,
 *     format?: string,
 *     utf8?: bool,
 *     stateless?: bool,
 * }
 * @psalm-type AliasConfig = array{
 *     alias: string,
 *     deprecated?: array{package:string, version:string, message?:string},
 * }
 * @psalm-type RoutesConfig = array{
 *     "when@dev"?: array<string, RouteConfig|ImportConfig|AliasConfig>,
 *     "when@prod"?: array<string, RouteConfig|ImportConfig|AliasConfig>,
 *     "when@test"?: array<string, RouteConfig|ImportConfig|AliasConfig>,
 *     ...<string, RouteConfig|ImportConfig|AliasConfig>
 * }
 */
final class Routes
{
    /**
     * @param RoutesConfig $config
     *
     * @psalm-return RoutesConfig
     */
    public static function config(array $config): array
    {
        return $config;
    }
}
