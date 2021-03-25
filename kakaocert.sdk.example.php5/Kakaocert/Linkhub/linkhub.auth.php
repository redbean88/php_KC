<?php
/**
* =====================================================================================
* Class for develop interoperation with Linkhub APIs.
* Functionalities are authentication for Linkhub api products, and to support
* several base infomation(ex. Remain point).
*
* This module uses curl and openssl for HTTPS Request. So related modules must
* be installed and enabled.
*
* http://www.linkhub.co.kr
* Author : Kim Seongjun (pallet027@gmail.com)
* Contributor : Jeong Yohan (code@linkhub.co.kr)
* Written : 2017-08-29
* Updated : 2020-04-23
*
* Thanks for your interest.
* We welcome any suggestions, feedbacks, blames or anythings.
*
* Update Log
* - 2017/08/29 GetPartnerURL API added
* ======================================================================================
*/
class Linkhub
{
	const VERSION = '1.0';
	const ServiceURL = 'https://auth.linkhub.co.kr';
	private $__LinkID;
	private $__SecretKey;
	private $__requestMode = LINKHUB_COMM_MODE;

  public function getSecretKey(){
    return $this->__SecretKey;
  }
  public function getLinkID(){
    return $this->__LinkID;
  }
	private static $singleton = null;

	/**
	* 인스턴스 획득 (*java sdk 차이점 : 비추되어 있으며, newInstance를 통해 항상 신규 생성만 진행)
	*/
	public static function getInstance($LinkID,$secretKey)
	{
		if(is_null(Linkhub::$singleton)) {
			Linkhub::$singleton = new Linkhub();
		}
		Linkhub::$singleton->__LinkID = $LinkID;
		Linkhub::$singleton->__SecretKey = $secretKey;

		return Linkhub::$singleton;
	}

	/**
	* gzip 압축 해제
	*/
	public function gzdecode($data){
	    return gzinflate(substr($data, 10, -8));	//11번째 자리부터 시작하여 미지막에서 8자리를 제외한 값을 압축해제
	}

	/**
	  * java의 TokenBuilder.java의 build()메소드 기능을 수행 ( 통신 부분 분리 )
  	* 1.현재 클래스의  __requestMode가 "STREAM"이 아닐 경우(curl)(common.php에서 상수로 설정)
	  * 	1. 파라미터 url과 curl_init()함수를 이용하여 connection 인스턴스 생성 (php 내장 함수)
		* 	2. isPost 가 true 인 경우
	  * 		1. curl_setopt함수를 이용하여 포스트 전송 활성화
	  * 		2. curl_setopt함수를 이용하여 파라미터 postdata값을 body부분에 할당(php 내장 함수)
		*		3. 기본 설정값 할당
		* 		1. 파라미터 header값을 curl_setopt함수를 이용하여 header값 할당(php 내장 함수)
		* 		2. curl_setopt함수를 이용하여 반환 결과가 아닌 반환 데이터로 받을수 있도록 설정(php 내장 함수)
		* 		3. curl_setopt함수를 이용하여 "Accept-Encoding" 설정 (gzip,deflate)(php 내장 함수)
		*		4. 통신 진행 및 응답 코드 확인 후 정상 통신 실패시, 예외 발생
		*		5. mb_strpos()함수를 이용하여 압축 여부를 확인후, 압축되어 있을 경우, gzdecode()함수를 이용하여 압축해제
		*		6. 정상 통신 완료시 결과값 반환
		* 2.현재 클래스의  __requestMode가 "STREAM"일 경우
		*		1. http Context 생성
		*			1. 오류 발생시, 메시지 회신 설정
		*			2. http 프로토콜 설정 ( 1.0 )
		*			3-1. isPost 가 true 인 경우
		*				1. http 메소드 설정 (POST)
		*				2. http 배열의 content 속성에 파라미터 postdata 할당
		*			3-2. isPost 가 false 인 경우
		*				1. http 메소드 설정 (GET)
		*			4. header 생성
		*				1. 지역변수 header를 foreach를 이용, params변수의 http.header 속성에 할당
		*			5. params 파라미터로 stream_context_create() 함수를 이용 stream context 생성
		*			6. 통신 진행
		*			7. 응답 메시지를 mb_strpos()함수를 이용하여 압축 여부를 is_gzip에 할당(php 내장 함수)
		*			7. is_gzip 값이 true일 경우, gzdecode() 함수를 이용 압축 해제하여 response에 할당
		*			8. http_response_header[0]값으로 정상 통신 완료확인후, 결과값 반환
	  */
	private function executeCURL($url,$header = array(),$isPost = false, $postdata = null) {

		if($this->__requestMode != "STREAM") {
			$http = curl_init($url);	// 인스턴스 생성

			if($isPost) {
				curl_setopt($http, CURLOPT_POST,1);// 포스트 전송 활성화
				curl_setopt($http, CURLOPT_POSTFIELDS, $postdata);// body 할당
			}
			curl_setopt($http, CURLOPT_HTTPHEADER,$header);	//header 값을 할당 한다
			curl_setopt($http, CURLOPT_RETURNTRANSFER, TRUE);	// 이 설정을 해주어야만, 특정 객체에 반환 값을 담을 수 있다. 미사용시 화면에 직업 표출
			curl_setopt($http, CURLOPT_ENCODING, 'gzip,deflate'); //header의 "Accept-Encoding 부분에 설정"

			$responseJson = curl_exec($http);
			$http_status = curl_getinfo($http, CURLINFO_HTTP_CODE);

      if ($responseJson != true){
        throw new LinkhubException(curl_error($http));
      }

			curl_close($http); //io 자원 회수

      $is_gzip = 0 === mb_strpos($responseJson, "\x1f" . "\x8b" . "\x08");


      if ($is_gzip) {
          $responseJson = $this->gzdecode($responseJson);
      }

			if($http_status != 200) {
				throw new LinkhubException($responseJson);
			}

			return json_decode($responseJson);

		}
		else {
			if($isPost) {
				$params = array('http' => array(
				 'ignore_errors' => TRUE,
				        	 'method' => 'POST',
				 'protocol_version' => '1.0',
				     	 'content' => $postdata
					    ));
      } else {
				$params = array('http' => array(
				 		 'ignore_errors' => TRUE,
				   	 'method' => 'GET',
				'protocol_version' => '1.0',
				    ));
      }
  			if ($header !== null) {
		  		$head = "";
		  		foreach($header as $h) {
	  				$head = $head . $h . "\r\n";
	    		}
	    		$params['http']['header'] = substr($head,0,-2);
	  		}
	  		$ctx = stream_context_create($params);
	  		$response = file_get_contents($url, false, $ctx);

			$is_gzip = 0 === mb_strpos($response , "\x1f" . "\x8b" . "\x08");

			if($is_gzip){
				$response = $this->gzdecode($response);
			}

	  		if ($http_response_header[0] != "HTTP/1.1 200 OK") {
	    		throw new LinkhubException($response);
	  		}

			return json_decode($response);
		}
	}

	/**
	  * java의 TokenBuilder.java의 getTime메소드 기능을 수행 (*java sdk 차이점 : useLocalTime 옵션 없음)
  	* 1.현재 클래스의  __requestMode가 "STREAM"이 아닐 경우(curl)(common.php에서 상수로 설정)
	  * 	1. 전역 상수 ServiceURL에 /Time을 붙여 curl_init()함수를 이용하여 connection 인스턴스 생성 (php 내장 함수)
		* 																																(*java sdk 차이점 : proxy 옵션 없음)
	  * 	2. 통신 결과값 반환을 위한 옵션 설정 (성공 여부가 아닌 결과값으로 반환)
		*		3. 통신 진행 및 응답 코드 확인 후 정상 통신 실패시, 예외 발생
		*		4. 정상 통신 완료시 결과값 반환 (*java sdk 차이점 : gzip 처리 없음)
		* 2.현재 클래스의  __requestMode가 "STREAM"일 경우
		*		1. http Context 생성
		*			1. 오류 발생시, 메시지 회신 설정
		*			2. http 프로토콜 설정 ( 1.0 )
		*			3. http 메소드 설정 (get)
		*			4. header 생성
		*				1. Connection: close 할당
		*				2. 지역변수 header를 foreach를 이용, params변수의 http.header 속성에 할당
		*			5. params 파라미터로 stream_context_create() 함수를 이용 stream context 생성
		*			6. 통신 진행 및 응답 코드 확인 후 정상 통신 실패시, 예외 발생
		*			7. 정상 통신 완료시 결과값 반환 (*java sdk 차이점 : gzip 처리 없음)
	  */
	public function getTime()
	{
		if($this->__requestMode != "STREAM") {
			$http = curl_init(Linkhub::ServiceURL.'/Time');

			curl_setopt($http, CURLOPT_RETURNTRANSFER, TRUE);	//성공 여부가 아닌 데이터 결과값을 반환

			$response = curl_exec($http);

			$http_status = curl_getinfo($http, CURLINFO_HTTP_CODE);

      if ($response != true){
        throw new LinkhubException(curl_error($http));
      }

			curl_close($http);	//자원 회수

      if($http_status != 200) {
				throw new LinkhubException($response);
			}
			return $response;

		} else {
			$header = array();
			$header[] = 'Connection: close';	//통신 완료 후, 닫기 처리
			$params = array('http' => array(
				 'ignore_errors' => TRUE,	//오류 발생시에도, 메시지 회신(default : false)
				'protocol_version' => '1.0',	// http 프로토콜 버전(default : 1.0)
				 'method' => 'GET'	// http 메소드 설정 (default : get)
   		    ));
			if ($header !== null) {	//해더 생성
		  		$head = "";
		  		foreach($header as $h) {
	  				$head = $head . $h . "\r\n";
	    		}
	    		$params['http']['header'] = substr($head,0,-2);	//마지막 \r\n 제거
	  		}

	  		$ctx = stream_context_create($params);	// steam context 생성

									//file_get_contents(주소, 상대경로 활성화 옵션(true/false), 해더)
	  		$response = file_get_contents(LInkhub::ServiceURL.'/Time', false, $ctx);

			if ($http_response_header[0] != "HTTP/1.1 200 OK") {
	    		throw new LinkhubException($response);
	  		}
			return $response;
		}
	}

	/**
	  * java의 TokenBuilder.java의 build()메소드 기능을 수행
	  * 1. 전역 상수 ServiceID에 /Token을 붙여 uri 생성
	  * 2. TokenRequest 인스턴스 생성 및 기본값 할
	  * 3. TokenRequest 인스턴스를 json string으로 변경 (이하 jsonString)
	  * 4. digestTarget 생성
	  * 	1. http 메소드 설정(post)
	  * 	2. jsonString의 해쉬값(md5) 생성 후 , base64 인코딩 진행한 값 추가
	  * 	3. API 서버 시간 추가
	  * 	4. forwardIP가 null이 아니거나 ''문자가 아닐 경우, forwardIP 값 추가
	  *		5. 전역 상수 version 값 추가
	  *		6. 지역 변수 uri 값 추가
		* 5. digest 생성
		*		1. __SecretKey의 -_값을 치환하여, base64_encode인코딩한 값을 생성 (이하 인코딩시크릿키)
		*		2. sha1 방식으로 인코딩시크릿키와 digestTarget 값을 hash_hmac 함수를 이용하여, 바이너리데이터 생성
		*		3. 바이너리데이터를 base64_encode인코딩 처리
		* 6. API서버와의 통싱을 위한 설정값을 header 할당
		*		1. x-lh-date : API서버에서 해당 값을 기준으로 유효기간을 확인(현재 시간)
		* 	2. x-lh-version
		*		3. x-lh-forwarded: forwardIP가 null이 아니거나 ''문자가 아닐 경우, forwardIP 값 추가
		*		4. "LINKHUB" + LINKID + digest를 합쳐 "Authorization"에 값을 할당
 		*		5. Accept-Encoding: gzip,deflate
 		*		6. Content-Type : Application/json
 		*		7. Connection: close
 		*	7. uri , header , post전송 여부(true) , postdata를 아규먼트로 executeCURL를 호출
	  */
	public function getToken($ServiceID, $access_id, array $scope = array() , $forwardIP = null)
	{
		$xDate = $this->getTime();

		$uri = '/' . $ServiceID . '/Token';
		$header = array();

		$TokenRequest = new TokenRequest();	// 다른부분에서 사용하지 않는다면, 생성자에 필수값을 설정하면 좋지 않을까?
		$TokenRequest->access_id = $access_id;
		$TokenRequest->scope = $scope;

		$postdata = json_encode($TokenRequest);

		$digestTarget = 'POST'.chr(10);	//chr(10) - 라인피드
		$digestTarget = $digestTarget.base64_encode(md5($postdata,true)).chr(10);
		$digestTarget = $digestTarget.$xDate.chr(10);
		if(!(is_null($forwardIP) || $forwardIP == '')) {
			$digestTarget = $digestTarget.$forwardIP.chr(10);
		}
		$digestTarget = $digestTarget.Linkhub::VERSION.chr(10);
		$digestTarget = $digestTarget.$uri;

		//strtr은 대상 문자열 길이 만큼만 변경됨
		//'-_', '+/'처리는 base64_encode url 지원을 위한 처리(-_ 문자는 base64 기본코드에 미존재 )
		$digest = base64_encode(hash_hmac('sha1',$digestTarget,base64_decode(strtr($this->__SecretKey, '-_', '+/')),true));

		$header[] = 'x-lh-date: '.$xDate;
		$header	[] = 'x-lh-version: '.Linkhub::VERSION;
		if(!(is_null($forwardIP) || $forwardIP == '')) {
			$header[] = 'x-lh-forwarded: '.$forwardIP;
		}

		$header[] = 'Authorization: LINKHUB '.$this->__LinkID.' '.$digest;
		$header	[] = 'Accept-Encoding: gzip,deflate';
		$header	[] = 'Content-Type: Application/json';
		$header	[] = 'Connection: close';

		return $this->executeCURL(Linkhub::ServiceURL.$uri , $header,true,$postdata);

	}


	public function getBalance($bearerToken, $ServiceID)
	{
		$header = array();
		$header[] = 'Authorization: Bearer '.$bearerToken;
		$header[] = 'Accept-Encoding: gzip,deflate';
		$header[] = 'Connection: close';

		$uri = '/'.$ServiceID.'/Point';

		$response = $this->executeCURL(Linkhub::ServiceURL . $uri,$header);
		return $response->remainPoint;

	}

	public function getPartnerBalance($bearerToken, $ServiceID)
	{
		$header = array();
		$header[] = 'Authorization: Bearer '.$bearerToken;
		$header[] = 'Accept-Encoding: gzip,deflate';
		$header[] = 'Connection: close';

		$uri = '/'.$ServiceID.'/PartnerPoint';

		$response = $this->executeCURL(Linkhub::ServiceURL . $uri,$header);
		return $response->remainPoint;
	}

  /*
  * 파트너 포인트 충전 팝업 URL 추가 (2017/08/29)
  */
  public function getPartnerURL($bearerToken, $ServiceID, $TOGO)
	{
		$header = array();
		$header[] = 'Authorization: Bearer '.$bearerToken;
		$header[] = 'Accept-Encoding: gzip,deflate';
		$header[] = 'Connection: close';

		$uri = '/'.$ServiceID.'/URL?TG='.$TOGO;

		$response = $this->executeCURL(Linkhub::ServiceURL . $uri, $header);
		return $response->url;
	}
}

class TokenRequest
{
	public $access_id;
	public $scope;
}

class LinkhubException extends Exception
{
	public function __construct($response, Exception $previous = null) {
       $Err = json_decode($response);
       if(is_null($Err)) {
       		parent::__construct($response, -99999999);
       }
       else {
       		parent::__construct($Err->message, $Err->code);
       }
    }

    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }

}

?>
