<?php

namespace Drupal\vactory_api_key_auth\Authentication\Provider;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * HTTP Basic authentication provider.
 */
class ApiKeyAuth implements AuthenticationProviderInterface {
  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The user auth service.
   *
   * @var \Drupal\user\UserAuthInterface
   */
  protected $userAuth;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a HTTP basic authentication provider object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    // Only apply this validation if request has a valid accept value.
    return $this->getKey($request) !== FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request) {
    // Load config entity.
    $api_key_entities = \Drupal::entityTypeManager()
      ->getStorage('api_key')
      ->loadMultiple();

    // @todo: use entityTypeManager for a direct lookup for the key
    // no loop
    foreach ($api_key_entities as $key_item) {
      if ($this->getKey($request) == $key_item->key) {
        $accounts = $this->entityTypeManager->getStorage('user')->loadByProperties(['uid' => $key_item->user_uuid]);
        $account = reset($accounts);

        if (isset($account)) {
          return $account;
        }
        break;
      }
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function cleanup(Request $request) {}

  /**
   * {@inheritdoc}
   */
  public function handleException(ExceptionEvent $event) {
    $exception = $event->getThrowable();
    if ($exception instanceof AccessDeniedHttpException) {
      $event->setThrowable(new UnauthorizedHttpException('Invalid consumer origin.', $exception));

      return TRUE;
    }

    return FALSE;
  }

  /**
   * Retrieve key from request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object that the service will respond to.
   *
   * @return bool
   *   True if api key is present
   */
  public function getKey(Request $request) {
    // Exempt edit/delete form route.
    $route_name = \Drupal::routeMatch()->getRouteName();
    if (strstr($route_name, 'entity.api_key')) {
      return FALSE;
    }

    $form_api_key = $request->get('api_key');

    if (!empty($form_api_key)) {
      return $form_api_key;
    }

    $query_api_key = $request->query->get('api_key');
    if (!empty($query_api_key)) {
      return $query_api_key;
    }

    $header_api_key = $request->headers->get('apikey');
    if (!empty($header_api_key)) {
      return $header_api_key;
    }
    return FALSE;
  }

}
