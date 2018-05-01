<?php
namespace LaravelRocket\Generator\Objects\OpenAPI;

use function ICanBoogie\singularize;

class ActionOld
{
    public const CONTEXT_TYPE_LIST        = 'list';
    public const CONTEXT_TYPE_SHOW        = 'show';
    public const CONTEXT_TYPE_STORE       = 'store';
    public const CONTEXT_TYPE_UPDATE      = 'update';
    public const CONTEXT_TYPE_DESTROY     = 'destroy';
    public const CONTEXT_TYPE_ME          = 'me';
    public const CONTEXT_TYPE_ME_SUB_DATA = 'me_sub_data';
    public const CONTEXT_TYPE_AUTH        = 'auth';
    public const CONTEXT_TYPE_AUTH_SNS    = 'auth_sns';
    public const CONTEXT_TYPE_UNKNOWN     = 'unknown';
    public const CONTEXT_TYPE_PASSWORD    = 'password';

    protected const SPECIAL_ACTIONS = [
        'post:signin'          => [
            'controller' => 'AuthController',
            'action'     => 'postSignIn',
        ],
        'post:signup'          => [
            'controller' => 'AuthController',
            'action'     => 'postSignUp',
        ],
        'post:signout'         => [
            'controller' => 'AuthController',
            'action'     => 'postSignOut',
        ],
        'post:forgot-password' => [
            'controller' => 'PasswordController',
            'action'     => 'forgotPassword',
        ],
        'post:token/refresh'   => [
            'controller' => 'AuthController',
            'action'     => 'postRefreshToken',
        ],
        'get:me'               => [
            'controller' => 'MeController',
            'action'     => 'getMe',
        ],
        'put:me'               => [
            'controller' => 'MeController',
            'action'     => 'putMe',
        ],
    ];

    /** @var string */
    protected $path;

    /** @var string */
    protected $method;

    /** @var string */
    protected $httpMethod;

    /** @var \TakaakiMizuno\SwaggerParser\Objects\Base */
    protected $info;

    /** @var \LaravelRocket\Generator\Objects\OpenAPI\PathElement[] $elements */
    protected $elements;

    /** @var string */
    protected $controllerName = '';

    /** @var string[] */
    protected $params = [];

    /** @var bool $usePagination */
    protected $usePagination = false;

    /** @var string $requestName */
    protected $requestName = '';

    /** @var \LaravelRocket\Generator\Objects\OpenAPI\OpenAPISpec */
    protected $spec;

    /** @var \LaravelRocket\Generator\Objects\OpenAPI\Definition|null */
    protected $response;

    /** @var \LaravelRocket\Generator\Objects\OpenAPI\Request */
    protected $request;

    /** @var string */
    protected $repositoryName = '';

    /** @var bool */
    protected $hasParent = false;

    /**
     * @var array
     */
    protected $actionContext = [
        'type'             => self::CONTEXT_TYPE_UNKNOWN,
        'targetRepository' => '',
        'targetModel'      => '',
        'parentRepository' => '',
        'parentModel'      => '',
        'parentFilters'    => [],
        'targetFilters'    => [],
        'data'             => [],
    ];

    /**
     * @param \LaravelRocket\Generator\Objects\OpenAPI\PathElement[] $elements
     * @param string                                                 $httpMethod
     * @param string                                                 $path
     * @param \TakaakiMizuno\SwaggerParser\Objects\Base              $info
     * @param \LaravelRocket\Generator\Objects\OpenAPI\OpenAPISpec   $spec
     *
     * @return array
     */
    public static function getAllCandidates($elements, $httpMethod, $path, $info, $spec)
    {
        $httpMethod = strtolower($httpMethod);
        $actions    = [];
        $elements   = array_reverse($elements);

        if (starts_with('/', $path)) {
            $path = substr($path, 1);
        }

        $specialKey = implode(':', [$httpMethod, $path]);
        if (array_key_exists($specialKey, self::SPECIAL_ACTIONS)) {
            $actionInfo = self::SPECIAL_ACTIONS[$specialKey];

            $actions[] = new static($actionInfo['controller'], $actionInfo['method'], $httpMethod, $path, $info, [], $spec);

            return $actions;
        }

        // Check SNS SignIn
        if ($httpMethod === 'post' && preg_match('/^signin\/([^\/]+)$/', $path, $matches)) {
            $name      = camel_case($matches[1]);
            $actions[] = new static(
                ucfirst($name).'AuthControllerController',
                'post'.ucfirst($name).'SignIn',
                $httpMethod, $path, $info, [
                'sns' => $name,
            ], $spec
            );

            return $actions;
        }

        $params = [];
        foreach ($elements as $element) {
            if ($element->isVariable()) {
                $params[] = $element->variableName();
            }
            $params = array_reverse($params);
        }

        // GET/POST /users
        if ($elements[0]->isPlural()) {
            $controller = title_case(snake_case(singularize($elements[0]->elementName())));
            switch ($httpMethod) {
                case 'get':
                    $method    = 'index';
                    $actions[] = new static($controller, $method, $httpMethod, $path, $info, $params, $spec);
                    break;
                case 'post':
                    $method    = 'store';
                    $actions[] = new static($controller, $method, $httpMethod, $path, $info, $params, $spec);
                    break;
            }
        }

        // GET/PUT/DELETE /users/{id}
        if (count($elements) >= 2 && $elements[0]->isVariable() && $elements[1]->isPlural()) {
            $controller = title_case(snake_case(singularize($elements[1]->elementName())));
            switch ($httpMethod) {
                case 'get':
                    $method    = 'show';
                    $actions[] = new static($controller, $method, $httpMethod, $path, $info, $params, $spec);
                    break;
                case 'put':
                case 'patch':
                    $method    = 'update';
                    $actions[] = new static($controller, $method, $httpMethod, $path, $info, $params, $spec);
                    break;
                case 'delete':
                    $method    = 'destroy';
                    $actions[] = new static($controller, $method, $httpMethod, $path, $info, $params, $spec);
                    break;
            }
        }

        // GET/POST/PUT/DELETE /users/info
        if (count($elements) >= 2 && !$elements[0]->isVariable() && $elements[1]->isPlural()) {
            $controller = title_case(snake_case(singularize($elements[1]->elementName())));
            switch ($httpMethod) {
                case 'get':
                    $method    = 'get'.ucfirst(camel_case($elements[0]->elementName()));
                    $actions[] = new static($controller, $method, $httpMethod, $path, $info, $params, $spec);
                    break;
                case 'post':
                    $method    = 'post'.ucfirst(camel_case($elements[0]->elementName()));
                    $actions[] = new static($controller, $method, $httpMethod, $path, $info, $params, $spec);
                    break;
                case 'put':
                case 'patch':
                    $method    = 'put'.ucfirst(camel_case($elements[0]->elementName()));
                    $actions[] = new static($controller, $method, $httpMethod, $path, $info, $params, $spec);
                    break;
                case 'delete':
                    $method    = 'delete'.ucfirst(camel_case($elements[0]->elementName()));
                    $actions[] = new static($controller, $method, $httpMethod, $path, $info, $params, $spec);
                    break;
            }
        }

        // GET/POST/PUT/DELETE /users/{id}/friends => UserFriendController
        if (count($elements) >= 3 && $elements[0]->isPlural() &&
            $elements[1]->isVariable() && $elements[2]->isPlural()) {
            $controllerOne = title_case(snake_case(singularize($elements[2]->elementName()))).
                title_case(snake_case(singularize($elements[0]->elementName())));
            $controllerTwo = title_case(snake_case(singularize($elements[2]->elementName())));
            switch ($httpMethod) {
                case 'get':
                    $method    = 'index';
                    $actions[] = new static($controllerOne, $method, $httpMethod, $path, $info, $params, $spec);
                    $method    = 'get'.ucfirst(camel_case($elements[0]->elementName()));
                    $actions[] = new static($controllerTwo, $method, $httpMethod, $path, $info, $params, $spec);
                    break;
                case 'post':
                    $method    = 'create';
                    $actions[] = new static($controllerOne, $method, $httpMethod, $path, $info, $params, $spec);
                    $method    = 'post'.ucfirst(camel_case($elements[0]->elementName()));
                    $actions[] = new static($controllerTwo, $method, $httpMethod, $path, $info, $params, $spec);
                    break;
                case 'put':
                case 'patch':
                    $method    = 'put'.ucfirst(camel_case($elements[0]->elementName()));
                    $actions[] = new static($controllerTwo, $method, $httpMethod, $path, $info, $params, $spec);
                    break;
                case 'delete':
                    $method    = 'delete'.ucfirst(camel_case($elements[0]->elementName()));
                    $actions[] = new static($controllerTwo, $method, $httpMethod, $path, $info, $params, $spec);
                    break;
            }
        }

        // GET/PUT/DELETE /users/{userId}/friends/{friendId} => UserFriendController
        if (count($elements) >= 4 && $elements[0]->isVariable() && $elements[1]->isPlural() &&
            $elements[2]->isVariable() && $elements[3]->isPlural()) {
            $controllerOne = title_case(snake_case(singularize($elements[1]->elementName()))).
                title_case(snake_case(singularize($elements[1]->elementName())));
            switch ($httpMethod) {
                case 'get':
                    $method    = 'show';
                    $actions[] = new static($controllerOne, $method, $httpMethod, $path, $info, $params, $spec);
                    break;
                case 'put':
                case 'patch':
                    $method    = 'update';
                    $actions[] = new static($controllerOne, $method, $httpMethod, $path, $info, $params, $spec);
                    break;
                case 'delete':
                    $method    = 'destroy';
                    $actions[] = new static($controllerOne, $method, $httpMethod, $path, $info, $params, $spec);
                    break;
            }
        }

        return array_reverse($actions);
    }

    /**
     * Action constructor.
     *
     * @param string                                               $controllerName
     * @param string                                               $method
     * @param string                                               $httpMethod
     * @param string                                               $path
     * @param \TakaakiMizuno\SwaggerParser\Objects\Base            $info
     * @param string[]                                             $params
     * @param \LaravelRocket\Generator\Objects\OpenAPI\OpenAPISpec $spec
     */
    public function __construct($controllerName, $method, $httpMethod, $path, $info, $params = [], $spec)
    {
        $this->controllerName = $controllerName;
        $this->method         = $method;
        $this->httpMethod     = strtolower($httpMethod);
        $this->path           = $path;
        $this->elements       = PathElement::parsePath($this->path, $this->httpMethod);
        $this->info           = $info;
        $this->spec           = $spec;

        $this->setParams($params);
        $this->setResponse();
        $this->setRequest();
        $this->guessActionContext();
    }

    /**
     * @return string
     */
    public function getControllerName(): string
    {
        return $this->controllerName;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getHttpMethod(): string
    {
        return $this->httpMethod;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return string[]
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @return \TakaakiMizuno\SwaggerParser\Objects\Base
     */
    public function getInfo()
    {
        return $this->info;
    }

    /**
     * @return \LaravelRocket\Generator\Objects\OpenAPI\Definition
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return \LaravelRocket\Generator\Objects\OpenAPI\Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return string
     */
    public function getRepositoryName(): string
    {
        return $this->repositoryName;
    }

    /**
     * @return string
     */
    public function getRouteName(): string
    {
        return $this->controllerName.'Controller@'.$this->method;
    }

    /**
     * @return string
     */
    public function getRouteIdentifier(): string
    {
        return camel_case(lcfirst($this->controllerName)).'.'.$this->method;
    }

    /**
     * @return bool
     */
    public function hasParent(): bool
    {
        return $this->hasParent;
    }

    /**
     * @param array $params
     */
    protected function setParams($params)
    {
        $this->params = [];
        foreach ($params as $param) {
            $this->params[] = '$'.$param;
        }
    }

    public function getQueryParameters()
    {
        $ret = [];
        foreach ($this->info->parameters as $parameter) {
            if ($parameter->in === 'query') {
                $ret[] = $parameter->name;
            }
        }

        return $ret;
    }

    public function getBodyParameters()
    {
        $ret = [];
        foreach ($this->info->parameters as $parameter) {
            if ($parameter->in === 'formData') {
                $ret[] = $parameter->name;
            }
        }

        return $ret;
    }

    protected function setResponse()
    {
        $responses = $this->info->responses;
        foreach ($responses as $statusCode => $response) {
            if (substr($statusCode, 0, 1) === '2') {
                $schema         = $response->schema;
                $ref            = $schema->{'$ref'};
                $this->response = $this->spec->findDefinition($ref);
                if ($this->httpMethod === 'delete') {
                } elseif ($this->response->getType() === Definition::TYPE_MODEL) {
                    $model                = $this->response->getModelName();
                    $this->repositoryName = $model.'Repository';
                } elseif ($this->response->getType() === Definition::TYPE_LIST) {
                    $model                = $this->response->getListItem()->getModelName();
                    $this->repositoryName = $model.'Repository';
                }

                return;
            }
        }

        $this->response = null;
    }

    protected function setRequest()
    {
        $this->request = new Request($this->controllerName, $this->method, $this->httpMethod, $this->info, $this->response, $this->spec);
    }

    protected function guessActionContext()
    {
        /** @var \LaravelRocket\Generator\Objects\OpenAPI\PathElement[] $elements */
        $elements = array_reverse($this->elements);

        if ($this->controllerName === 'AuthController' && $this->httpMethod === 'post') {
            $this->actionContext = [
                'type'             => static::CONTEXT_TYPE_AUTH,
                'targetRepository' => 'UserRepository',
            ];
        } elseif (ends_with($this->controllerName, 'AuthController') && $this->httpMethod === 'post') {
            $this->actionContext = [
                'type'             => static::CONTEXT_TYPE_AUTH_SNS,
                'targetRepository' => $this->repositoryName,
                'data'             => $this->params,
            ];
        } elseif ($elements[0]->elementName() === 'me') {
            switch ($this->httpMethod) {
                case 'get':
                    $this->actionContext = [
                        'type'             => static::CONTEXT_TYPE_ME,
                        'targetRepository' => 'UserRepository',
                    ];
                    break;
                case 'put':
                    $this->actionContext = [
                        'type'             => static::CONTEXT_TYPE_ME,
                        'targetRepository' => 'UserRepository',
                    ];
            }
        } elseif ($elements[0]->isPlural()) {
            $table     = $this->spec->findTable($elements[0]->elementName());
            $modelName = 'Base';
            if (!empty($table)) {
                $modelName = $table->getModelName();
            }
            switch ($this->httpMethod) {
                case 'get':
                    $this->actionContext = [
                        'type'             => static::CONTEXT_TYPE_LIST,
                        'targetModel'      => $modelName,
                        'targetRepository' => $modelName.'Repository',
                    ];
                    break;
                case 'post':
                    $this->actionContext = [
                        'type'             => static::CONTEXT_TYPE_STORE,
                        'targetModel'      => $modelName,
                        'targetRepository' => $modelName.'Repository',
                    ];
            }
        } elseif (count($elements) >= 2 && $elements[0]->isVariable() && $elements[1]->isPlural()) {
            $table     = $this->spec->findTable($elements[1]->elementName());
            $modelName = 'Base';
            if (!empty($table)) {
                $modelName = $table->getModelName();
            }
            switch ($this->httpMethod) {
                case 'get':
                    $this->actionContext = [
                        'type'             => static::CONTEXT_TYPE_SHOW,
                        'targetModel'      => $modelName,
                        'targetRepository' => $modelName.'Repository',
                    ];
                    break;
                case 'put':
                case 'patch':
                    $this->actionContext = [
                        'type'             => static::CONTEXT_TYPE_UPDATE,
                        'targetModel'      => $modelName,
                        'targetRepository' => $modelName.'Repository',
                    ];
                    break;
                case 'delete':
                    $this->actionContext = [
                        'type'             => static::CONTEXT_TYPE_DESTROY,
                        'targetModel'      => $modelName,
                        'targetRepository' => $modelName.'Repository',
                        'data'             => [
                            'model' => $modelName,
                        ],
                    ];
                    break;
            }
            $this->actionContext['targetFilters'] = [$elements[0]->variableName() => '$'.$elements[0]->variableName()];
        }

        $this->actionContext['targetModel'] = str_replace('Repository', '', $this->actionContext['targetRepository']);

        if (count($elements) >= 2 && $elements[1]->elementName() === 'me') {
            $this->hasParent                         = true;
            $this->actionContext['parentRepository'] = 'UserRepository';
            $this->actionContext['parentModel']      = 'User';
            $this->actionContext['parentFilters']    = ['user_id' => '$id'];
        } elseif (count($elements) >= 3 && $elements[0]->isPlural() && $elements[1]->isVariable() && $elements[2]->isPlural()) {
            $table     = $this->spec->findTable($elements[2]->elementName());
            $modelName = 'Base';
            if (!empty($table)) {
                $modelName = $table->getModelName();
            }
            $this->hasParent                         = true;
            $this->actionContext['parentRepository'] = $modelName.'Repository';
            $this->actionContext['parentModel']      = $modelName;
            $this->actionContext['parentFilters']    = [singularize($elements[2]->elementName()).'_'.$elements[1]->variableName() => '$'.$elements[1]->variableName()];
        }
    }

    /**
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getActionContext($name, $default = null)
    {
        return array_get($this->actionContext, $name, $default);
    }
}