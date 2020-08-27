<?php
use Slim\Slim;
use Slim\Views\Twig;
use Slim\Middleware\SessionCookie;

class Xhgui_ServiceContainer extends Pimple
{
    protected static $_instance;

    public static function instance()
    {
        if (empty(static::$_instance)) {
            static::$_instance = new self();
        }
        return static::$_instance;
    }

    public function __construct()
    {
        $this->_slimApp();
        $this->_services();
        $this->_controllers();
    }

    // Create the Slim app.
    protected function _slimApp()
    {
        $this['view'] = static function ($c) {
            $cacheDir = isset($c['config']['cache']) ? $c['config']['cache'] : XHGUI_ROOT_DIR . '/cache';

            // Configure Twig view for slim
            $view = new Twig();

            $view->twigTemplateDirs = [dirname(__DIR__) . '/templates'];
            $view->parserOptions = [
                'charset' => 'utf-8',
                'cache' => $cacheDir,
                'auto_reload' => true,
                'strict_variables' => false,
                'autoescape' => true
            ];

            return $view;
        };

        $this['app'] = $this->share(static function ($c) {
            if ($c['config']['timezone']) {
                date_default_timezone_set($c['config']['timezone']);
            }

            $app = new Slim($c['config']);

            // Enable cookie based sessions
            $app->add(new SessionCookie([
                'httponly' => true,
            ]));

            // Add renderer.
            $app->add(new Xhgui_Middleware_Render());

            $view = $c['view'];
            $view->parserExtensions = [
                new Xhgui_Twig_Extension($app)
            ];
            $app->view($view);

            return $app;
        });
    }

    /**
     * Add common service objects to the container.
     */
    protected function _services()
    {
        $this['config'] = Xhgui_Config::all();

        $this['db'] = $this->share(static function ($c) {
            $config = $c['config'];
            if (empty($config['db.options'])) {
                $config['db.options'] = [];
            }
            if (empty($config['db.driverOptions'])) {
                $config['db.driverOptions'] = [];
            }
            $mongo = new MongoClient($config['db.host'], $config['db.options'], $config['db.driverOptions']);
            $mongo->{$config['db.db']}->results->findOne();

            return $mongo->{$config['db.db']};
        });

        $this['pdo'] = $this->share(static function ($c) {
            return new PDO(
                $c['config']['pdo']['dsn'],
                $c['config']['pdo']['user'],
                $c['config']['pdo']['pass']
            );
        });

        $this['searcher.mongo'] = static function ($c) {
            return new Xhgui_Searcher_Mongo($c['db']);
        };

        $this['searcher.pdo'] = static function ($c) {
            return new Xhgui_Searcher_Pdo($c['pdo'], $c['config']['pdo']['table']);
        };

        $this['searcher'] = static function ($c) {
            $config = $c['config'];

            switch ($config['save.handler']) {
                case 'pdo':
                    return $c['searcher.pdo'];

                case 'mongodb':
                default:
                    return $c['searcher.mongo'];
            }
        };

        $this['saver.mongo'] = static function ($c) {
            $config = $c['config'];
            $config['save.handler'] = 'mongodb';

            return Xhgui_Saver::factory($config);
        };

        $this['saver'] = static function ($c) {
            return Xhgui_Saver::factory($c['config']);
        };
    }

    /**
     * Add controllers to the DI container.
     */
    protected function _controllers()
    {
        $this['watchController'] = static function ($c) {
            return new Xhgui_Controller_Watch($c['app'], $c['searcher']);
        };

        $this['runController'] = static function ($c) {
            return new Xhgui_Controller_Run($c['app'], $c['searcher']);
        };

        $this['customController'] = static function ($c) {
            return new Xhgui_Controller_Custom($c['app'], $c['searcher']);
        };

        $this['waterfallController'] = static function ($c) {
            return new Xhgui_Controller_Waterfall($c['app'], $c['searcher']);
        };

        $this['importController'] = static function ($c) {
            return new Xhgui_Controller_Import($c['app'], $c['saver'], $c['config']['upload.token']);
        };

        $this['metricsController'] = static function ($c) {
            return new Xhgui_Controller_Metrics($c['app'], $c['searcher']);
        };
    }

}
