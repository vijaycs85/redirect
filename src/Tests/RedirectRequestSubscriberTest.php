<?php

/**
 * @file
 * Contains \Drupal\redirect\Tests\RedirectRequestSubscriberTest
 */

namespace Drupal\redirect\Tests;

use Drupal\Core\Language\Language;
use Drupal\redirect\EventSubscriber\RedirectRequestSubscriber;
use Drupal\redirect\EventSubscriber\RedirectTerminateSubscriber;
use Drupal\Tests\UnitTestCase;
use PHPUnit_Framework_MockObject_MockObject;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;

/**
 * Tests the redirect logic.
 *
 * @group redirect
 */
class RedirectRequestSubscriberTest extends UnitTestCase {

  /**
   * Unit test of the RedirectRequestSubscriber::onKernelRequestCheckRedirect().
   */
  public function testRedirectLogic() {

    // USE CASE 1.1.
    // Route name is provided, the url is generated from the route, request
    // query string should be retained.

    // Set the matching route name.
    $route_name = 'test.route';
    // The request query.
    $request_query = array('key' => 'val');
    // The query defined by the redirect entity.
    $redirect_query = array('dummy' => 'value');
    // The expected final query. This query must contain values defined
    // by the redirect entity and values from the accessed url.
    $final_query = $redirect_query + $request_query;

    $url_generator = $this->getMockBuilder('Drupal\Core\Routing\UrlGenerator')
      ->disableOriginalConstructor()
      ->getMock();
    $url_generator->expects($this->once())
      ->method('generateFromRoute')
      ->with($route_name, array(), array('absolute' => TRUE, 'query' => $final_query))
      ->will($this->returnValue('dummy_value'));
    $url_generator->expects($this->never())
      ->method('generateFromPath');

    $redirect = $this->getRedirectStub('getRedirectRouteName', $route_name, $redirect_query);
    $this->assertOnKernelRequestCheckRedirect($url_generator, $redirect, $request_query, TRUE);

    // USE CASE 1.2.
    // Route name is provided, query string should NOT be retained.

    // Final query must not contain the request query.
    $final_query = $redirect_query;

    $url_generator = $this->getMockBuilder('Drupal\Core\Routing\UrlGenerator')
      ->disableOriginalConstructor()
      ->getMock();
    $url_generator->expects($this->once())
      ->method('generateFromRoute')
      ->with($route_name, array(), array('absolute' => TRUE, 'query' => $final_query))
      ->will($this->returnValue('dummy_value'));
    $url_generator->expects($this->never())
      ->method('generateFromPath');

    $redirect = $this->getRedirectStub('getRedirectRouteName', $route_name, $redirect_query);
    $this->assertOnKernelRequestCheckRedirect($url_generator, $redirect, $request_query, FALSE);

    // USE CASE 2.
    // No redirect found - none of the UrlGenerator generate functions will
    // trigger.

    $url_generator = $this->getMockBuilder('Drupal\Core\Routing\UrlGenerator')
      ->disableOriginalConstructor()
      ->getMock();
    $url_generator->expects($this->never())
      ->method('generateFromRoute');
    $url_generator->expects($this->never())
      ->method('generateFromPath');

    $this->assertOnKernelRequestCheckRedirect($url_generator, NULL, $request_query, TRUE);

    // USE CASE 3.1
    // Absolute redirect url is provided, the url is generated from the path,
    // request query string should be retained.

    // Set the matching route name.
    $route_url = 'route_url';
    // The request query.
    $request_query = array('key' => 'val');
    // The query defined by the redirect entity.
    $redirect_query = array('dummy' => 'value');
    // The expected final query. This query must contain values defined
    // by the redirect entity and values from the accessed url.
    $final_query = $redirect_query + $request_query;

    $url_generator = $this->getMockBuilder('Drupal\Core\Routing\UrlGenerator')
      ->disableOriginalConstructor()
      ->getMock();
    $url_generator->expects($this->once())
      ->method('generateFromPath')
      ->with($route_url, array('external' => TRUE, 'query' => $final_query))
      ->will($this->returnValue('dummy_value'));
    $url_generator->expects($this->never())
      ->method('generateFromRoute');

    $redirect = $this->getRedirectStub('getRedirectUrl', $route_url . '?' . http_build_query($redirect_query));
    $this->assertOnKernelRequestCheckRedirect($url_generator, $redirect, $request_query, TRUE);

    // USE CASE 3.2.
    // Absolute redirect url is provided, request query string should NOT be
    // retained.

    // The final query must not contain the request query.
    $final_query = $redirect_query;

    $url_generator = $this->getMockBuilder('Drupal\Core\Routing\UrlGenerator')
      ->disableOriginalConstructor()
      ->getMock();
    $url_generator->expects($this->once())
      ->method('generateFromPath')
      ->with($route_url, array('external' => TRUE, 'query' => $final_query))
      ->will($this->returnValue('dummy_value'));
    $url_generator->expects($this->never())
      ->method('generateFromRoute');

    $redirect = $this->getRedirectStub('getRedirectUrl', $route_url . '?' . http_build_query($redirect_query));
    $this->assertOnKernelRequestCheckRedirect($url_generator, $redirect, $request_query, FALSE);
  }

  /**
   * Test the redirect logging.
   */
  public function testRedirectLogging() {
    // By providing the X-Redirect-ID we expect to trigger the logic that calls
    // setting the access and count on the redirect logic.

    $redirect = $this->getMockBuilder('Drupal\redirect\Entity\Redirect')
      ->disableOriginalConstructor()
      ->getMock();
    $redirect->expects($this->once())
      ->method('setLastAccessed')
      ->with(REQUEST_TIME);
    $redirect->expects($this->once())
      ->method('setCount')
      ->with(1);
    $redirect->expects($this->once())
      ->method('save');

    $repository = $this->getMockBuilder('Drupal\redirect\RedirectRepository')
      ->disableOriginalConstructor()
      ->getMock();
    $repository->expects($this->any())
      ->method('load')
      ->will($this->returnValue($redirect));

    $subscriber = new RedirectTerminateSubscriber($repository);
    $post_response_event = $this->getPostResponseEvent(array('X-Redirect-ID' => 1));
    $subscriber->onKernelTerminateLogRedirect($post_response_event);

    // By not providing the the X-Redirect-ID the logging logic must not
    // trigger.

    $redirect = $this->getMockBuilder('Drupal\redirect\Entity\Redirect')
      ->disableOriginalConstructor()
      ->getMock();
    $redirect->expects($this->never())
      ->method('setLastAccessed');
    $redirect->expects($this->never())
      ->method('setCount');
    $redirect->expects($this->never())
      ->method('save');

    $repository = $this->getMockBuilder('Drupal\redirect\RedirectRepository')
      ->disableOriginalConstructor()
      ->getMock();
    $repository->expects($this->any())
      ->method('load')
      ->will($this->returnValue($redirect));

    $subscriber = new RedirectTerminateSubscriber($repository);
    $post_response_event = $this->getPostResponseEvent();
    $subscriber->onKernelTerminateLogRedirect($post_response_event);
  }

  /**
   * Instantiates the subscriber and runs onKernelRequestCheckRedirect()
   *
   * @param $url_generator
   *   Url generator object.
   * @param $redirect
   *   The redirect entity.
   * @param array $request_query
   *   The query that is supposed to come via request.
   * @param bool $retain_query
   *   Flag if to retain the query through the redirect.
   */
  protected function assertOnKernelRequestCheckRedirect($url_generator, $redirect, $request_query, $retain_query) {

    $checker = $this->getMockBuilder('Drupal\redirect\RedirectChecker')
      ->disableOriginalConstructor()
      ->getMock();
    $checker->expects($this->any())
      ->method('canRedirect')
      ->will($this->returnValue(TRUE));
    $checker->expects($this->any())
      ->method('isLoop')
      ->will($this->returnValue(FALSE));

    $context = $this->getMock('Symfony\Component\Routing\RequestContext');

    $subscriber = new RedirectRequestSubscriber(
      $url_generator,
      $this->getRedirectRepositoryStub('findMatchingRedirect', $redirect),
      $this->getLanguageManagerStub(),
      $this->getConfigFactoryStub(array('redirect.settings' => array('passthrough_querystring' => $retain_query))),
      $checker,
      $context
    );

    // Run the main redirect method.
    $subscriber->onKernelRequestCheckRedirect($this->getGetResponseEventStub('non-existing', http_build_query($request_query)));
  }

  /**
   * Gets the redirect repository mock object.
   *
   * @param $method
   *   Method to mock - either load() or findMatchingRedirect().
   * @param $redirect
   *   The redirect object to be returned.
   *
   * @return PHPUnit_Framework_MockObject_MockObject
   *   The redirect repository.
   */
  protected function getRedirectRepositoryStub($method, $redirect) {
    $repository = $this->getMockBuilder('Drupal\redirect\RedirectRepository')
      ->disableOriginalConstructor()
      ->getMock();

    $repository->expects($this->any())
      ->method($method)
      ->will($this->returnValue($redirect));

    return $repository;
  }

  /**
   * Gets the redirect mock object.
   *
   * @param $method
   *   Method to mock - either getRedirectRouteName() or getRedirectUrl().
   * @param $value
   *   Value to be returned by the mocked method.
   * @param array $query
   *   In case the redirect has a valid route name the query that will be
   *   appended to to the resulting url.
   * @param int $status_code
   *   The redirect status code.
   *
   * @return PHPUnit_Framework_MockObject_MockObject
   *   The mocked redirect object.
   */
  protected function getRedirectStub($method, $value, $query = array(), $status_code = 301) {
    $redirect = $this->getMockBuilder('Drupal\redirect\Entity\Redirect')
      ->disableOriginalConstructor()
      ->getMock();
    $redirect->expects($this->once())
      ->method($method)
      ->will($this->returnValue($value));
    $redirect->expects($this->any())
      ->method('getRedirectRouteParameters')
      ->will($this->returnValue(array()));
    $redirect->expects($this->any())
      ->method('getStatusCode')
      ->will($this->returnValue($status_code));

    if (!empty($query)) {
      $redirect->expects($this->once())
        ->method('getRedirectOption')
        ->will($this->returnValue($query));
    }

    return $redirect;
  }

  /**
   * Gets post response event.
   *
   * @param array $headers
   *   Headers to be set into the response.
   *
   * @return \Symfony\Component\HttpKernel\Event\PostResponseEvent
   *   The post response event object.
   */
  protected function getPostResponseEvent($headers = array()) {
    $http_kernel = $this->getMockBuilder('\Symfony\Component\HttpKernel\HttpKernelInterface')
      ->getMock();
    $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')
      ->disableOriginalConstructor()
      ->getMock();

    $response = new Response('', 301, $headers);

    return new PostResponseEvent($http_kernel, $request, $response);
  }

  /**
   * Gets response event object.
   *
   * @param $path_info
   * @param $query_string
   *
   * @return GetResponseEvent
   */
  protected function getGetResponseEventStub($path_info, $query_string) {

    $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')
      ->disableOriginalConstructor()
      ->getMock();
    $request->expects($this->any())
      ->method('getQueryString')
      ->will($this->returnValue($query_string));
    $request->expects($this->any())
      ->method('getPathInfo')
      ->will($this->returnValue($path_info));
    $request->expects($this->any())
      ->method('getScriptName')
      ->will($this->returnValue('index.php'));
    $request->expects($this->any())
      ->method('isMethod')
      ->with('GET')
      ->will($this->returnValue(TRUE));

    $request->attributes = new ParameterBag();

    $http_kernel = $this->getMockBuilder('\Symfony\Component\HttpKernel\HttpKernelInterface')
      ->getMock();
    return new GetResponseEvent($http_kernel, $request, 'test');
  }

  /**
   * Gets the language manager mock object.
   *
   * @return \Drupal\language\ConfigurableLanguageManagerInterface|PHPUnit_Framework_MockObject_MockObject
   */
  protected function getLanguageManagerStub() {
    $language_manager = $this->getMockBuilder('Drupal\language\ConfigurableLanguageManagerInterface')
      ->getMock();
    $language_manager->expects($this->any())
      ->method('getCurrentLanguage')
      ->will($this->returnValue(new Language(array('id' => 'en'))));

    return $language_manager;
  }

}
