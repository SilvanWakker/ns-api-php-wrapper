<?php

namespace NsApi;

use Psr\Http\Message\ResponseInterface;

class Client {

  /**
   * The base URL of the NS webservices.
   */
  private const API_URL = 'http://webservices.ns.nl';

  /**
   * Paths to the various endpoints the NS API supports.
   */
  private const PRICES_ENDPOINT = 'ns-api-prijzen-v3';
  private const CURRENT_DEPARTURE_TIMES_ENDPOINT = 'ns-api-avt';
  private const DISRUPTIONS_AND_RAIL_WORK_ENDPOINT = 'ns-api-storingen';
  private const STATIONS_LIST_ENDPOINT = 'ns-api-stations-v2';
  private const TRAVEL_ADVICE_ENDPOINT = 'ns-api-treinplanner';


  /**
   * The username ans password to the NS API.
   *
   * @var string
   */
  private $username;
  private $password;

  /**
   * Construct a new NS API client.
   *
   * @param string $username
   *   The NS API username.
   * @param string $password
   *   The NS API password.
   */
  public function __construct(string $username, string $password) {
    $this->username = $username;
    $this->password = $password;
  }

  /**
   * Get the prices of a journey.
   *
   * You may optionally pass a station to pass through and/or the date of the
   * journey.
   *
   * @param string $from
   *   The abbreviation code, medium name, full name or synonym of the departure
   *   station.
   * @param string $to
   *   The abbreviation code, medium name, full name or synonym of the
   *   destination station.
   * @param string $through
   *   The abbreviation code, medium name, full name or synonym of the departure
   *   station.
   * @param string $date_time
   *   Gets the prices for this date. Format should me ddMMyyyy.
   *
   * @return \SimpleXMLElement
   *   The parsed XML from the response body.
   */
  public function getPrices(string $from, string $to, string $through = NULL, string $date_time = NULL) : \SimpleXMLElement {
    $query = [
      'from' => $from,
      'to' => $to,
    ];

    if ($through !== NULL) {
      $query['via'] = $through;
    }
    if ($date_time !== NULL) {
      $query['dateTime'] = $date_time;
    }

    $request = $this->requestNsData(self::PRICES_ENDPOINT, $query);
    $xml_body = $request->getBody()->getContents();
    return new \SimpleXMLElement($xml_body);
  }

  /**
   * Get the actual departure times of a station.
   *
   * @param string $station
   *   The abbreviation code, medium name, full name or synonym of the departure
   *   station.
   *
   * @return \SimpleXMLElement
   *   The parsed XML from the response body.
   *
   * @throws \Exception
   *   When the response code is not 200.
   */
  public function getCurrentDepartureTimes(string $station) : \SimpleXMLElement {
    $query = [
      'station'=> $station,
    ];

    $request = $this->requestNsData(self::CURRENT_DEPARTURE_TIMES_ENDPOINT, $query);

    if ($request->getStatusCode() == 200) {
      $xml_body = $request->getBody()->getContents();
      return new \SimpleXMLElement($xml_body);
    }
    else {
      throw new \Exception('Could not fetch data.');
    }
  }

  /**
   * Get information about disruptions and rail work.
   *
   * The following information can be retrieved:
   * 1. Actual disruptions. This includes unplanned disruptions and actual rail
   *    work.
   * 2. Planned rail work. This includes just the planned rail work.
   * 3. Actual disruptions for specified station. This includes unplanned
   *    disruptions and actual rail work.
   *
   * Note that any combination of these parameters is possible, but might not
   * return useful/usable values.
   *
   * @param string $station
   *   The abbreviation code, medium name, full name or synonym of the departure
   *   station. Returns scenario #3 mentioned above.
   * @param boolean $actual
   *   Indicator if the actual disruptions should be returned. This affects two
   *   things:
   *   1. the unplanned disruptions at the time of the request
   *   2. Planned rail work that will be performed within two hours from the
   *      time of the request.
   *   Returns scenario #1 mentioned above.
   * @param boolean $unplanned
   *   Indicates if the planned rail works of the coming two weeks should be
   *   returned. Returns scenario #2 as mentioned above.
   *
   * @return \SimpleXMLElement
   *   The parsed XML from the response body.
   *
   * @throws \Exception
   *   When the response code is not 200.
   */
  public function getDisruptionsAndRailWork(string $station = NULL, bool $actual= NULL, bool $unplanned = NULL) : \SimpleXMLElement {
    $query = [];
    if ($station !== NULL) {
      $query['station'] = $station;
    }
    if ($actual !== NULL) {
      $query['actual'] = $actual;
    }
    if ($unplanned !== NULL) {
      // When the value of "unplanned" is true, the response contains de planned
      // rail works. It's the opposite of what the name implies, therefore we
      // flip it to make our system more logical.
      $query['unplanned'] = !$unplanned;
    }

    $request = $this->requestNsData(self::DISRUPTIONS_AND_RAIL_WORK_ENDPOINT, $query);

    if ($request->getStatusCode() == 200) {
      $xml_body = $request->getBody()->getContents();
      return new \SimpleXMLElement($xml_body);
    }
    else {
      throw new \Exception('Could not fetch data.');
    }
  }

  /**
   * Retrieve the list of all stations.
   *
   * Please note that you will want to cache this data, as the call is quite
   * heavy and returns a large result set.
   *
   * @return \SimpleXMLElement
   *   The parsed XML from the response body.
   *
   * @throws \Exception
   *   When the response code is not 200.
   */
  public function getStationsList() : \SimpleXMLElement {
    $request = $this->requestNsData(self::STATIONS_LIST_ENDPOINT);

    if ($request->getStatusCode() == 200) {
      $xml_body = $request->getBody()->getContents();
      return new \SimpleXMLElement($xml_body);
    }
    else {
      throw new \Exception('Could not fetch data.');
    }
  }

  /**
   * Get travel advice for travelling from station to station.
   *
   * @param string $from
   *   The abbreviation code, medium name, full name or synonym of the departure
   *   station.
   * @param string $to
   *   The abbreviation code, medium name, full name or synonym of the departure
   *   station.
   * @param string $through
   *   The abbreviation code, medium name, full name or synonym of the departure
   *   station.
   * @param integer $previous_advices
   *   The number of travel advices to return in the past of the
   *   departure/arrival time. There is no guarantee the response will contain
   *   exactly this amount. The default and maximum is 5.
   * @param integer $next_advices
   *   The number of travel advices to return in the future of the
   *   departure/arrival time. There is no guarantee the response will contain
   *   exactly this amount. The default and maximum is 5.
   * @param string $date_time
   *   An ISO8601 formatted date of the departure or arrival time, you may
   *   specify this via the departure parameter. If left NULL, the current time
   *   will be used. For example: 2012-02-21T15:50.
   * @param boolean $departure
   *   Indicates if the date_time parameter represent the departure or arrival
   *   time. Departure is TRUE, arrival is FALSE.
   * @param boolean $high_speed_trains_allowed
   *   Indicates if high speed trains may be included in the travel advice.
   * @param boolean $year_card
   *   Indicates if the travel advice should take a year card into account. In
   *   certain scenarios this opens up new travel advices.
   *
   * @return \SimpleXMLElement
   *   The parsed XML from the response body.
   *
   * @throws \Exception
   *   When the response code is not 200.
   */
  public function getTravelAdvice(string $from, string $to, string $through = NULL, int $previous_advices = 5, int $next_advices = 5, string $date_time = NULL, bool $departure = TRUE, bool $high_speed_trains_allowed = TRUE, bool $year_card = FALSE) : \SimpleXMLElement {
    $query = [
      'fromStation' => $from,
      'toStation' => $to,
    ];
    if ($through !== NULL) {
      $query['viaStation'] = $through;
    }
    $query['previous_advices'] = $previous_advices;
    $query['next_advices'] = $next_advices;
    if ($date_time !== NULL) {
      $query['dateTime'] = $date_time;
    }
    $query['departure'] = $departure;
    $query['hslAllowed'] = $high_speed_trains_allowed;
    $query['yearCArd'] = $year_card;

    $request = $this->requestNsData(self::TRAVEL_ADVICE_ENDPOINT, $query);

    if ($request->getStatusCode() == 200) {
      $xml_body = $request->getBody()->getContents();
      return new \SimpleXMLElement($xml_body);
    }
    else {
      throw new \Exception('Could not fetch data.');
    }
  }

  /**
   * Request data from the NS API.
   *
   * @param string $endpoint
   *   One of the endpoint constants.
   * @param array $query_parameters
   *   An associative array of query parameters.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   An HTTP response.
   */
  protected function requestNsData(string $endpoint, array $query_parameters = NULL) : ResponseInterface {
    $request_url = self::API_URL . '/' . $endpoint;
    if ($query_parameters !== NULL) {
      $request_url .= '?' . http_build_query($query_parameters);
    }

    $http_client = new \GuzzleHttp\Client();
    $request = $http_client->get($request_url, [
      'auth' => [
        $this->username,
        $this->password,
      ],
    ]);

    return $request;
  }

}
