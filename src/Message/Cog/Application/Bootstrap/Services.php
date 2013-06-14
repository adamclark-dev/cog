<?php

namespace Message\Cog\Application\Bootstrap;

use Message\Cog\Bootstrap\ServicesInterface;
use Message\Cog\Application\Environment;
use Message\Cog\Routing\RouteCollection;
use Message\Cog\DB;

/**
 * Cog services bootstrap.
 *
 * Registers Cog service definitions when the application is loaded.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 * @author James Moss <james@message.co.uk>
 */
class Services implements ServicesInterface
{
	/**
	 * Register the services to the given service container.
	 *
	 * @param object $serviceContainer The service container
	 */
	public function registerServices($serviceContainer)
	{
		$serviceContainer['profiler'] = $serviceContainer->share(function() {
			return new \Message\Cog\Debug\Profiler(null, null, false);
		});

		$env = new Environment;
		$serviceContainer['environment'] = function() use ($env) {
			return $env;
		};
		$serviceContainer['env'] = function($c) {
			return $c['environment']->get();
		};

		$serviceContainer['db.connection'] = $serviceContainer->share(function($s) {
			return new \Message\Cog\DB\Adapter\MySQLi\Connection(array(
				'host'		=> $s['cfg']->db->hostname,
				'user'		=> $s['cfg']->db->user,
				'password' 	=> $s['cfg']->db->pass,
				'db'		=> $s['cfg']->db->name,
				'charset'	=> $s['cfg']->db->charset,
			));
		});

		$serviceContainer['db.query'] = function($s) {
			return new \Message\Cog\DB\Query($s['db.connection']);
		};

		// shortcut for easier access
		$serviceContainer['db'] = function($s) {
			return $s['db.query'];
		};

		$serviceContainer['db.transaction'] = function($s) {
			return new \Message\Cog\DB\Transaction($s['db.connection']);
		};

		$serviceContainer['db.nested_set_helper'] = function($s) {
			return new \Message\Cog\DB\NestedSetHelper($s['db.query'], $s['db.transaction']);
		};

		$serviceContainer['cache'] = $serviceContainer->share(function($s) {
			$adapterClass = (extension_loaded('apc') && ini_get('apc.enabled')) ? 'APC' : 'Filesystem';
			$adapterClass = '\\Message\\Cog\\Cache\\Adapter\\' . $adapterClass;
			$cache        = new \Message\Cog\Cache\Instance(
				new $adapterClass
			);
			$cache->setPrefix(implode('.', array(
				$s['app.loader']->getAppName(),
				$s['environment']->get(),
				$s['environment']->installation(),
			)));

			return $cache;
		});

		$serviceContainer['event'] = function() {
			return new \Message\Cog\Event\Event;
		};

		$serviceContainer['event.dispatcher'] = $serviceContainer->share(function($c) {
			return new \Message\Cog\Event\Dispatcher($c);
		});

		$serviceContainer['routes'] = $serviceContainer->share(function($c) {
			return new \Message\Cog\Routing\CollectionManager($c['reference_parser']);
		});

		$serviceContainer['routing.matcher'] = function($c) {
			return new \Message\Cog\Routing\UrlMatcher($c['routes.compiled'], $c['http.request.context']);
		};

		$serviceContainer['routing.generator'] = function($c) {
			$generator = new \Message\Cog\Routing\UrlGenerator($c['routes.compiled'], $c['http.request.context']);
			$generator->setCsrfSecrets($c['http.session'], $c['routing.csrf_secret']);
			
			return $generator;
		};

		// @todo - Get this out of the config  rather than hardcoding it and change it for every site
		$serviceContainer['routing.csrf_secret'] = function($c) {
			return 'THIS IS A SECRET DO NOT SHARE IT AROUND';
		};

		// Service for the templating delegation engine
		$serviceContainer['templating'] = $serviceContainer->share(function($c) {
			return new \Message\Cog\Templating\DelegatingEngine(
				array(
					// Twig templating engine
					new \Message\Cog\Templating\TwigEngine(
						$c['templating.twig.environment'],
						$c['templating.view_name_parser']
					),
					$c['templating.php.engine'],
				)
			);
		});

		$serviceContainer['templating.view_name_parser'] = $serviceContainer->share(function($c) {
			return new \Message\Cog\Templating\ViewNameParser(
				$c,
				$c['reference_parser'],
				array(
					'twig',
					'php',
				)
			);
		});

		$serviceContainer['templating.twig.environment'] = $serviceContainer->share(function($c) {
			$twigEnvironment = new \Twig_Environment(
				new \Message\Cog\Templating\TwigFilesystemLoader('/', $c['templating.view_name_parser']),
				array(
					'cache' => 'cog://tmp',
					'auto_reload' => true,
				)
			);

			$twigEnvironment->addGlobal('flashes', $c['http.session']->getFlashBag()->all());
			$twigEnvironment->addGlobal('cfg', $c['cfg']);

			$actionsHelper = new \Message\Cog\Templating\Helper\Actions(
				$c['http.fragment_handler'],
				$c['reference_parser']
			);

			$twigEnvironment->addExtension(new \Message\Cog\Templating\Twig\Extension\HttpKernel($actionsHelper));
			$twigEnvironment->addExtension(new \Message\Cog\Templating\Twig\Extension\Routing($c['routing.generator']));
			$twigEnvironment->addExtension(new \Message\Cog\Templating\Twig\Extension\Translation($c['translator']));

			return $twigEnvironment;
		});

		$serviceContainer['templating.php.engine'] = $serviceContainer->share(function($c) {

			$actionsHelper = new \Message\Cog\Templating\Helper\Actions(
				$c['http.fragment_handler'],
				$c['reference_parser']
			);

			// Plain PHP templating engine
			return new \Message\Cog\Templating\PhpEngine(
				$c['templating.view_name_parser'],
				new \Symfony\Component\Templating\Loader\FilesystemLoader(
					$c['app.loader']->getBaseDir()
				),
				array(
					new \Symfony\Component\Templating\Helper\SlotsHelper,
					$actionsHelper,
					new \Message\Cog\Templating\Helper\Routing($c['routing.generator']),
					new \Message\Cog\Templating\Helper\Translation($c['translator']),
				)
			);
		});

		$serviceContainer['http.kernel'] = function($c) {
			return new \Message\Cog\HTTP\Kernel(
				$c['event.dispatcher'],
				new \Symfony\Component\HttpKernel\Controller\ControllerResolver
			);
		};

		$serviceContainer['http.session'] = $serviceContainer->share(function($c) {
			$storage = new \Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

			// Use an array as the session storage when running unit tests
			if ('test' === $c['env']) {
				$storage = new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
			}

			return new \Message\Cog\HTTP\Session(
				$storage,
				null,
				new \Symfony\Component\HttpFoundation\Session\Flash\FlashBag('__cog_flashes')
			);
		});

		$serviceContainer['http.cookies'] = $serviceContainer->share(function() {
			return new \Message\Cog\HTTP\CookieCollection;
		});

		$serviceContainer['http.fragment_handler'] = $serviceContainer->share(function($c) {
			return new \Symfony\Component\HttpKernel\Fragment\FragmentHandler(array(
				new \Symfony\Component\HttpKernel\Fragment\InlineFragmentRenderer($c['http.kernel'])
			), ('local' === $c['env']));
		});

		$serviceContainer['http.uri_signer'] = $serviceContainer->share(function() {
			return new \Symfony\Component\HttpKernel\UriSigner(time());
		});

		$serviceContainer['response_builder'] = $serviceContainer->share(function($c) {
			return new \Message\Cog\Controller\ResponseBuilder(
				$c['templating']
			);
		});

		$serviceContainer['config.loader'] = $serviceContainer->share(function($c) {
			if ('local' === $c['env']) {
				// When running locally, don't use the cache loader
				return new \Message\Cog\Config\Loader(
					$c['app.loader']->getBaseDir() . 'config/',
					$c['environment']
				);
			}
			else {
				return new \Message\Cog\Config\LoaderCache(
					$c['app.loader']->getBaseDir() . 'config/',
					$c['environment'],
					$c['cache']
				);
			}
		});

		$serviceContainer['cfg'] = $serviceContainer->share(function($c) {
			return new \Message\Cog\Config\Registry($c['config.loader']);
		});

		$serviceContainer['module.locator'] = $serviceContainer->share(function($c) {
			return new \Message\Cog\Module\Locator($c['class.loader']->getPrefixes());
		});

		$serviceContainer['module.loader'] = $serviceContainer->share(function($c) {
			return new \Message\Cog\Module\Loader($c['module.locator'], $c['bootstrap.loader'], $c['event.dispatcher']);
		});

		$serviceContainer['task.collection'] = $serviceContainer->share(function($c) {
			return new \Message\Cog\Console\Task\Collection;
		});

		// Functions
		$serviceContainer['fns.text'] = $serviceContainer->share(function() {
			return new \Message\Cog\Functions\Text;
		});
		$serviceContainer['fns.utility'] = $serviceContainer->share(function($c) {
			return new \Message\Cog\Functions\Utility($c['module.loader']);
		});
		$serviceContainer['fns.debug'] = $serviceContainer->share(function($c) {
			return new \Message\Cog\Functions\Debug;
		});

		$serviceContainer['reference_parser'] = $serviceContainer->share(function($c) {
			return new \Message\Cog\Module\ReferenceParser($c['module.locator'], $c['fns.utility']);
		});

		// Filesystem
		$serviceContainer['filesystem.stream_wrapper_manager'] = $serviceContainer->share(function($c) {
			return new \Message\Cog\Filesystem\StreamWrapperManager;
		});

		$serviceContainer['filesystem.stream_wrapper'] = function($c) {
			$wrapper = new \Message\Cog\Filesystem\StreamWrapper;
			$wrapper->setReferenceParser($c['reference_parser']);
			$wrapper->setMapping($c['filesystem.stream_wrapper_mapping']);

			return $wrapper;
		};

		$serviceContainer['filesystem.stream_wrapper_mapping'] = function($c) {
			$baseDir = $c['app.loader']->getBaseDir();
			$mapping = array(
				// Maps cog://tmp/* to /tmp/* (in the installation)
				"/^\/tmp\/(.*)/us" 	  => $baseDir.'tmp/$1',
				"/^\/public\/(.*)/us" => $baseDir.'public/$1',
			);

			return $mapping;
		};

		$serviceContainer['filesystem'] = function($c) {
			return new \Message\Cog\Filesystem\Filesystem;
		};

		$serviceContainer['filesystem.finder'] = function($c) {
			return new \Message\Cog\Filesystem\Finder;
		};

		// Application Contexts
		$serviceContainer['app.context.web'] = $serviceContainer->share(function($c) {
			return new \Message\Cog\Application\Context\Web($c);
		});

		$serviceContainer['app.context.console'] = $serviceContainer->share(function($c) {
			return new \Message\Cog\Application\Context\Console($c);
		});

		// Validator
		$serviceContainer['validator'] = function($c) {
			return new \Message\Cog\Validation\Validator(
				new \Message\Cog\Validation\Loader(
					new \Message\Cog\Validation\Messages,
					array(
						new \Message\Cog\Validation\Rule\Date,
						new \Message\Cog\Validation\Rule\Number,
//						new \Message\Cog\Validation\Rule\Iterable, - not working yet
						new \Message\Cog\Validation\Rule\Text,
						new \Message\Cog\Validation\Rule\Other,
						new \Message\Cog\Validation\Filter\Text,
						new \Message\Cog\Validation\Filter\Type,
						new \Message\Cog\Validation\Filter\Other,
					)
				)
			);
		};

		$serviceContainer['security.salt'] = $serviceContainer->share(function() {
			return new \Message\Cog\Security\Salt;
		});

		$serviceContainer['security.hash'] = $serviceContainer->share(function($c) {
			return new \Message\Cog\Security\Hash\Bcrypt($c['security.salt']);
		});

		// Hardcode to en_GB for the moment. In the future this can be determined
		// from properties on the route or the session object
		$serviceContainer['locale'] = $serviceContainer->share(function($c) {
			return new \Message\Cog\Localisation\Locale('en_GB');
		});

		$serviceContainer['translator'] = $serviceContainer->share(function ($c) {
			$selector = new \Message\Cog\Localisation\MessageSelector;
			$id       = $c['locale']->getId();

			$translator = new \Message\Cog\Localisation\Translator($id, $selector);
			$translator->setFallbackLocale($c['locale']->getFallback());

			$translator->addLoader('yml', new \Message\Cog\Localisation\YamlFileLoader);

			$dir = $c['app.loader']->getBaseDir().'/translations';
			foreach($c['filesystem.finder']->in($dir) as $file) {
				$translator->addResource('yml', $file->getPathname(), $file->getFilenameWithoutExtension());
			}
			
			return $translator;
		});
	}
}