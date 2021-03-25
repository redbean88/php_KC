<?php
/**
 * =====================================================================================
 * Class for base module for Kakaocert API SDK. It include base functionality for
 * RESTful web service request and parse json result. It uses Linkhub module
 * to accomplish authentication APIs.
 *
 * This module uses curl and openssl for HTTPS Request. So related modules must
 * be installed and enabled.
 *
 * http://www.linkhub.co.kr
 * Author : Jeogn Yohan (code@linkhub.co.kr)
 * Written : 2020-04-23
 * Updated : 2020-09-08
 *
 * Thanks for your interest.
 * We welcome any suggestions, feedbacks, blames or anythings.
 * ======================================================================================
 */

require_once 'Linkhub/linkhub.auth.php';

class KakaocertService
{
  const ServiceID = 'KAKAOCERT';
  const ServiceURL = 'https://kakaocert-api.linkhub.co.kr';
  const Version = '1.0';

 /**
 * JAVA와 다르게 php에서는 array를 아래의 자료형으로 사용 가능.
 * 1. array
 * 2. map
 * 3. list
 * 4. set
 */
  private $Token_Table = array();
  private $Linkhub;
  private $IsTest = false;
  private $IPRestrictOnOff = true;
  private $scopes = array();
  private $__requestMode = LINKHUB_COMM_MODE;

  /*
  * 생성자
  * php는 오버로딩이 되지 않아 하나만 만들수 있다.
  */
  public function __construct($LinkID, $SecretKey)
  {
    $this->Linkhub = Linkhub::getInstance($LinkID, $SecretKey);
    $this->scopes[] = 'member';
    $this->scopes[] = '310';
    $this->scopes[] = '320';
    $this->scopes[] = '330';
  }

  protected function AddScope($scope)
  {
    $this->scopes[] = $scope;
  }

  public function IPRestrictOnOff($V)
  {
      $this->IPRestrictOnOff = $V;
  }

  /**
    * java의 KakaocertServiceImp.java의 getSessionToken()메소드 기능을 수행
    * 1. 전역변수 Token_Table에 해당 이용기관명을 키로하는 token이 존재시, 지역변수 targetToken(이하 token)에 할당
    * 2. token이 null이 아니면, 만료기한을 확인
 	  *   1. targetToken의 expiration 값과, Linkhub 클래스의 getTime()함수로 받은 API서버 시간(UTCTime)을 비교하여,
    *      expiration보다 API서버 시간보다 이전이면 true, 아니면 false를 Refresh 변수에 할당
    * 3. Refresh 값이 true이면
    *   1. ServiceID, 이용기관코드, scopes, IPRestrictOnOff(null일 경우 와일드카드(*)로 설정)를 파라미터로 하여
    *    Linkhub 클래스의 getToken()함수를 호출, 반환 값을 targetToken에 할당
    *   2.Token_Table 이용기관코드를 키로하는 targetToken 할당
    * 4. targetToken의 session_token속성을 반환
    */
  private function getsession_Token($CorpNum)
  {
    $targetToken = null;

    /**
    * 토큰 재사용
    */
    if (array_key_exists($CorpNum, $this->Token_Table)) {
      $targetToken = $this->Token_Table[$CorpNum];
    }

    $Refresh = false; // 	(*java sdk : expired 변수명으로 사용)

    if (is_null($targetToken)) {
      $Refresh = true;
    } else {
      $Expiration = new DateTime($targetToken->expiration, new DateTimeZone("UTC"));

      $now = $this->Linkhub->getTime();
      $Refresh = $Expiration < $now;
    }

    if ($Refresh) {
      try {
        $targetToken = $this->Linkhub->getToken(KakaocertService::ServiceID, $CorpNum, $this->scopes, $this->IPRestrictOnOff ? null : "*");
      } catch (LinkhubException $le) {
        throw new KakaocertException($le->getMessage(), $le->getCode());
      }
      $this->Token_Table[$CorpNum] = $targetToken;
    }
    return $targetToken->session_token;
  }

/**
  * java의 KakaocertServiceImp.java의 httpPost메소드와 httpGet메소드의 기능을 수행
  * 1.현재 클래스의  __requestMode가 "STREAM"이 아닐 경우(curl)(common.php에서 상수로 설정)
  *  1. 전역 상수 ServiceURL에 URL을 붙여 curl_init()함수를 이용하여 connection 인스턴스 생성 (php 내장 함수)
  *  2. header 부분의 Authorization에 Bearer + 이용기관 코드를 키로 갖는 인증용 토큰(getSession_Token 함수의 반환 값)을 할당
  *  3. header 부분에 Content-Type: Application/json 할당
  *  4. isPost 가 true 인 경우
  *   1. curl_setopt함수를 이용하여 포스트 전송 활성화
  * 	2. curl_setopt함수를 이용하여 파라미터 postdata값을 body부분에 할당(php 내장 함수)
  * 	3. Linkhub 클래스의 getTime()함수로 받은 API서버 시간(UTCTime)받아 xDate변수에 할당
  *   4. digestTarget 생성
  * 	 1. http 메소드 설정(post)
  * 	 2. jsonString의 해쉬값(md5) 생성 후 , base64 인코딩 진행한 값 추가
  * 	 3. API 서버 시간(xDate) 추가
  *		 4. Linkhub 클래스의 전역 상수 version 값 추가
  *   5. digest 생성
  *		 1. Linkhub 클래스의 __SecretKey의 -_값을 치환하여, base64_encode인코딩한 값을 생성 (이하 인코딩시크릿키)
  *	   2. sha1 방식으로 인코딩시크릿키와 digestTarget 값을 hash_hmac 함수를 이용하여, 바이너리데이터 생성
  *	   3. 바이너리데이터를 base64_encode인코딩 처리
  *   6. API서버와의 통싱을 위한 설정값을 header 할당
  *		 1. x-lh-date : API서버에서 해당 값을 기준으로 유효기간을 확인(현재 시간)
  * 	 2. x-lh-version
  *		 4. Linkhub 클래스의 getLinkID()함수의 반환값(__LinkID)+ ' ' + digest를 합쳐 "x-kc-auth"에 값을 할당
  *  5. 기본 설정값 할당
  * 	 1. 파라미터 header값을 curl_setopt함수를 이용하여 header값 할당(php 내장 함수)
  * 	 2. curl_setopt함수를 이용하여 반환 결과가 아닌 반환 데이터로 받을수 있도록 설정(php 내장 함수)
  * 	 3. curl_setopt함수를 이용하여 "Accept-Encoding" 설정 (gzip,deflate)(php 내장 함수)
  *  6. 통신 진행 및 응답 코드를 지역 변수 http_status에 할당
  *  7. mb_strpos()함수를 이용하여 압축 여부를 확인후, 압축되어 있을 경우, Linkhub 클래스의 gzdecode()함수를 이용하여 압축해제
  *	 8. 통신 결과내의 contentType을 소문자로 변경하여, contentType변수에 할당
  *	 9. http_status 값이 200이 아닌 경우, 예외 발생
  *	 10. contentType이 application/pdf이 아닐 경우, responseJson 반환하며 아닐 경우, json_decode() 함수를 이용하여 객체화 후 반환
  * 2.현재 클래스의  __requestMode가 "STREAM"일 경우
  *  1. haeder 생성
  *   1. Accept-Encoding: gzip,deflate 할당
  *   2. Connection: close 할당
  *   3. Bearer + 이용기관코드를 파라미터로 getsession_Token()함수를 호출해 반환 받은 값을 Authorization 할당
  *   4. Content-Type: Application/json 할당
  *   5. Linkhub 클래스의 getTime()함수로 받은 API서버 시간(UTCTime)받아 xDate변수에 할당
  *   6. digestTarget 생성
  * 	 1. http 메소드 설정(post)
  * 	 2. jsonString의 해쉬값(md5) 생성 후 , base64 인코딩 진행한 값 추가
  * 	 3. API 서버 시간(xDate) 추가
  *		 4. Linkhub 클래스의 전역 상수 version 값 추가
  *   7. digest 생성
  *		 1. Linkhub 클래스의 __SecretKey의 -_값을 치환하여, base64_encode인코딩한 값을 생성 (이하 인코딩시크릿키)
  *	   2. sha1 방식으로 인코딩시크릿키와 digestTarget 값을 hash_hmac 함수를 이용하여, 바이너리데이터 생성
  *	   3. 바이너리데이터를 base64_encode인코딩 처리
  *   8. API서버와의 통싱을 위한 설정값을 header 할당
  *		 1. x-lh-date : API서버에서 해당 값을 기준으로 유효기간을 확인(현재 시간)
  * 	 2. x-lh-version
  *		 4. Linkhub 클래스의 getLinkID()함수의 반환값(__LinkID)+ ' ' + digest를 합쳐 "x-kc-auth"에 값을 할당
  *  2. http Context 생성
  *			1. 오류 발생시, 메시지 회신 설정
  *			2. http 프로토콜 설정 ( 1.0 )
  *			3. http 메소드 설정 (GET)
  *			4-1. isPost 가 true 인 경우
  *				1. http 메소드 설정 (POST)
  *				2. http 배열의 content 속성에 파라미터 postbody 할당
  *			5. header 생성
  *				1. 지역변수 header를 foreach를 이용, params변수의 http.header 속성에 할당
  *			6. params 파라미터로 stream_context_create() 함수를 이용 stream context 생성
  *			7. 통신 진행
  *			8. 응답 메시지를 mb_strpos()함수를 이용하여 압축 여부를 is_gzip에 할당(php 내장 함수)
  *			9. is_gzip 값이 true일 경우, gzdecode() 함수를 이용 압축 해제하여 response에 할당
  *			10. http_response_header[0]값이 "HTTP/1.1 200 OK"가 아닐 경우, 예외 반환
  *	    11. contentType이 application/pdf이 아닐 경우, responseJson 반환하며 아닐 경우, json_decode() 함수를 이용하여 객체화 후 반환
  */
  protected function executeCURL($uri, $ClientCode = null, $userID = null, $isPost = false, $action = null, $postdata = null, $isMultiPart = false, $contentsType = null)
  {
    if ($this->__requestMode != "STREAM") {
      $http = curl_init(KakaocertService::ServiceURL . $uri);
      $header = array();

      if (is_null($ClientCode) == false) {
        $header[] = 'Authorization: Bearer ' . $this->getsession_Token($ClientCode);
      }

      $header[] = 'Content-Type: Application/json';

      if ($isPost) {
        curl_setopt($http, CURLOPT_POST, 1); // 포스트 전송 활성화
        curl_setopt($http, CURLOPT_POSTFIELDS, $postdata);// body 할당

        $xDate = $this->Linkhub->getTime();

        $digestTarget = 'POST'.chr(10);
        $digestTarget = $digestTarget.base64_encode(md5($postdata,true)).chr(10);
        $digestTarget = $digestTarget.$xDate.chr(10);

        $digestTarget = $digestTarget.Linkhub::VERSION.chr(10);

        $digest = base64_encode(hash_hmac('sha1',$digestTarget,base64_decode(strtr($this->Linkhub->getSecretKey(), '-_', '+/')),true));

        $header[] = 'x-lh-date: '.$xDate;
        $header[] = 'x-lh-version: '.Linkhub::VERSION;
        $header[] = 'x-kc-auth: '.$this->Linkhub->getLinkID().' '.$digest;

      }

      curl_setopt($http, CURLOPT_HTTPHEADER, $header);
      curl_setopt($http, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($http, CURLOPT_ENCODING, 'gzip,deflate');

      $responseJson = curl_exec($http);
      $http_status = curl_getinfo($http, CURLINFO_HTTP_CODE);

      $is_gzip = 0 === mb_strpos($responseJson, "\x1f" . "\x8b" . "\x08");

      if ($is_gzip) {
        $responseJson = $this->Linkhub->gzdecode($responseJson);
      }

      $contentType = strtolower(curl_getinfo($http, CURLINFO_CONTENT_TYPE));

      curl_close($http);  //자원 회수

      if ($http_status != 200) {
        throw new KakaocertException($responseJson);
      }

      if( 0 === mb_strpos($contentType, 'application/pdf')) {
        return $responseJson;
      }
      return json_decode($responseJson);

    } else {
      $header = array();

      $header[] = 'Accept-Encoding: gzip,deflate';
      $header[] = 'Connection: close';
      if (is_null($ClientCode) == false) {
        $header[] = 'Authorization: Bearer ' . $this->getsession_Token($ClientCode);
      }

      if ($isMultiPart == false) {
        $header[] = 'Content-Type: Application/json';
        $postbody = $postdata;


        $xDate = $this->Linkhub->getTime();

        $digestTarget = 'POST'.chr(10);
        $digestTarget = $digestTarget.base64_encode(md5($postdata,true)).chr(10);
        $digestTarget = $digestTarget.$xDate.chr(10);

        $digestTarget = $digestTarget.Linkhub::VERSION.chr(10);

        $digest = base64_encode(hash_hmac('sha1',$digestTarget,base64_decode(strtr($this->Linkhub->getSecretKey(), '-_', '+/')),true));

        $header[] = 'x-lh-date: '.$xDate;
        $header[] = 'x-lh-version: '.Linkhub::VERSION;
        $header[] = 'x-kc-auth: '.$this->Linkhub->getLinkID().' '.$digest;


      }

      $params = array(
        'http' => array(
        'ignore_errors' => TRUE,
        'protocol_version' => '1.0',
        'method' => 'GET'
      ));

      if ($isPost) {
        $params['http']['method'] = 'POST';
        $params['http']['content'] = $postbody;
      }

      if ($header !== null) {
        $head = "";
        foreach ($header as $h) {
          $head = $head . $h . "\r\n";
        }
        $params['http']['header'] = substr($head, 0, -2);
      }

      $ctx = stream_context_create($params);
      $response = file_get_contents(KakaocertService::ServiceURL . $uri, false, $ctx);

      $is_gzip = 0 === mb_strpos($response, "\x1f" . "\x8b" . "\x08");

      if ($is_gzip) {
        $response = $this->Linkhub->gzdecode($response);
      }

      if ($http_response_header[0] != "HTTP/1.1 200 OK") {  //http_response_header는 file_get_contents()함수를 사용하여 통신시 자동으로 생성
        throw new KakaocertException($response);
      }

      foreach( $http_response_header as $k=>$v )
      {
        $t = explode( ':', $v, 2 ); //정규식 parser를 사용하지 않기 때문에 split보다 빠름
        if( preg_match('/^Content-Type:/i', $v, $out )) { //정규식으로 사용한 이유는?
          $contentType = trim($t[1]);
          if( 0 === mb_strpos($contentType, 'application/pdf')) { //steam이외 처리 와 동일 하도록  strtolower가 필요
            return $response;
          }
        }
      }

      return json_decode($response);
    }
  }

  public function requestESign($ClientCode, $RequestESign, $appUseYN = false)
  {
    $RequestESign->isAppUseYN = $appUseYN;

    $postdata = json_encode($RequestESign);
    return $this->executeCURL('/SignToken/Request', $ClientCode, null, true, null, $postdata);
  }

  public function getESignState($ClientCode, $receiptID)
  {
    if (is_null($receiptID) || empty($receiptID)) {
      throw new KakaocertException('접수아이디가 입력되지 않았습니다.');
    }

    $uri = '/SignToken/Status/' . $receiptID;

    $result = $this->executeCURL($uri, $ClientCode);

    $ResultESign = new ResultESign();
    $ResultESign->fromJsonInfo($result);
    return $ResultESign;
  }

  public function requestVerifyAuth($ClientCode, $RequestVerifyAuth)
  {
    //RequestVerifyAuth 객체를 JSON string으로 변환(php 내장함수)
    $postdata = json_encode($RequestVerifyAuth);
    //현재 클래스의 executeCURL를 반환한다 ( 반환값은 JSON String )
    return $this->executeCURL('/SignIdentity/Request', $ClientCode, null, true, null, $postdata)->receiptId;
  }

  public function getVerifyAuthState($ClientCode, $receiptID)
  {
      if (is_null($receiptID) || empty($receiptID)) {
          throw new KakaocertException('접수아이디가 입력되지 않았습니다.');
      }
      $result = $this->executeCURL('/SignIdentity/Status/' . $receiptID, $ClientCode);

      $ResultVerifyAuth = new ResultVerifyAuth();
      $ResultVerifyAuth->fromJsonInfo($result);
      return $ResultVerifyAuth;
  }

   public function requestCMS($ClientCode, $RequestCMS)
   {
     $postdata = json_encode($RequestCMS);
     return $this->executeCURL('/SignDirectDebit/Request', $ClientCode, null, true, null, $postdata)->receiptId;
   }

   public function getCMSState($ClientCode, $receiptID)
  {
      if (is_null($receiptID) || empty($receiptID)) {
          throw new KakaocertException('접수아이디가 입력되지 않았습니다.');
      }
      $result = $this->executeCURL('/SignDirectDebit/Status/' . $receiptID, $ClientCode);

      $ResultCMS = new ResultCMS();
      $ResultCMS->fromJsonInfo($result);
      return $ResultCMS;
  }

  public function verifyESign($ClientCode, $receiptID, $signature = null)
  {
    if (is_null($receiptID) || empty($receiptID)) {
      throw new KakaocertException('접수아이디가 입력되지 않았습니다.');
    }

    $uri = '/SignToken/Verify/' . $receiptID;

    if (!is_null($signature) || !empty($signature)) {
      $uri .= '/'.$signature;
    }

    return $this->executeCURL($uri, $ClientCode);
  }

  public function verifyAuth($ClientCode, $receiptID)
  {
    if (is_null($receiptID) || empty($receiptID)) {
      throw new KakaocertException('접수아이디가 입력되지 않았습니다.');
    }

    return $this->executeCURL('/SignIdentity/Verify/' . $receiptID, $ClientCode);
  }

  public function verifyCMS($ClientCode, $receiptID)
  {
     if (is_null($receiptID) || empty($receiptID)) {
       throw new KakaocertException('접수아이디가 입력되지 않았습니다.');
     }
     return $this->executeCURL('/SignDirectDebit/Verify/' . $receiptID, $ClientCode);
  }
} // end of KakaocertService

class KakaocertException extends Exception
{
    public function __construct($response, $code = -99999999, Exception $previous = null)
    {
        $Err = json_decode($response);
        if (is_null($Err)) {
            parent::__construct($response, $code);
        } else {
            parent::__construct($Err->message, $Err->code);
        }
    }

    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}

class RequestCMS
{
  public $CallCenterNum;
	public $Expires_in;
	public $PayLoad;
	public $ReceiverBirthDay;
	public $ReceiverHP;
	public $ReceiverName;
	public $SubClientID;
	public $TMSMessage;
	public $TMSTitle;

	public $isAllowSimpleRegistYN;
	public $isVerifyNameYN;

  public $BankAccountName;
	public $BankAccountNum;
	public $BankCode;
	public $ClientUserID;

}

class ResultCMS
{
  public $receiptID;
	public $regDT;
	public $state;

	public $expires_in;
	public $callCenterNum;

	public $allowSimpleRegistYN;
	public $verifyNameYN;
	public $payload;
	public $requestDT;
	public $expireDT;
	public $clientCode;
	public $clientName;
	public $tmstitle;
	public $tmsmessage;

	public $subClientName;
	public $subClientCode;
	public $viewDT;
	public $completeDT;
	public $verifyDT;

  /**
  * 현재 클래스의 변수에 json_decode 처리된 데이터 할당
  */
  public function fromJsonInfo($jsonInfo)
  {
    isset($jsonInfo->receiptID) ? $this->receiptID = $jsonInfo->receiptID : null;
    isset($jsonInfo->regDT) ? $this->regDT = $jsonInfo->regDT : null;
    isset($jsonInfo->state) ? $this->state = $jsonInfo->state : null;

    isset($jsonInfo->expires_in) ? $this->expires_in = $jsonInfo->expires_in : null;
    isset($jsonInfo->callCenterNum) ? $this->callCenterNum = $jsonInfo->callCenterNum : null;

    isset($jsonInfo->allowSimpleRegistYN) ? $this->allowSimpleRegistYN = $jsonInfo->allowSimpleRegistYN : null;
    isset($jsonInfo->verifyNameYN) ? $this->verifyNameYN = $jsonInfo->verifyNameYN : null;
    isset($jsonInfo->payload) ? $this->payload = $jsonInfo->payload : null;
    isset($jsonInfo->requestDT) ? $this->requestDT = $jsonInfo->requestDT : null;
    isset($jsonInfo->expireDT) ? $this->expireDT = $jsonInfo->expireDT : null;
    isset($jsonInfo->clientCode) ? $this->clientCode = $jsonInfo->clientCode : null;
    isset($jsonInfo->clientName) ? $this->clientName = $jsonInfo->clientName : null;
    isset($jsonInfo->tmstitle) ? $this->tmstitle = $jsonInfo->tmstitle : null;
    isset($jsonInfo->tmsmessage) ? $this->tmsmessage = $jsonInfo->tmsmessage : null;

    isset($jsonInfo->subClientName) ? $this->subClientName = $jsonInfo->subClientName : null;
    isset($jsonInfo->subClientCode) ? $this->subClientCode = $jsonInfo->subClientCode : null;
    isset($jsonInfo->viewDT) ? $this->viewDT = $jsonInfo->viewDT : null;
    isset($jsonInfo->completeDT) ? $this->completeDT = $jsonInfo->completeDT : null;
    isset($jsonInfo->verifyDT) ? $this->verifyDT = $jsonInfo->verifyDT : null;

  }
}

/**
* KC 본인인증(s315) 요청 객체
*/
class RequestVerifyAuth
{
  public $CallCenterNum;
	public $Expires_in;
	public $PayLoad;
	public $ReceiverBirthDay;
	public $ReceiverHP;
	public $ReceiverName;
	public $SubClientID;
	public $TMSMessage;
	public $TMSTitle;
	public $Token;
	public $isAllowSimpleRegistYN;
	public $isVerifyNameYN;
}
/**
* kc 본인인증(S315) 응답 객체
*/
class ResultVerifyAuth
{
  public $receiptID;
	public $regDT;
	public $state;

	public $expires_in;
	public $callCenterNum;

	public $allowSimpleRegistYN;
	public $verifyNameYN;
	public $payload;
	public $requestDT;
	public $expireDT;
	public $clientCode;
	public $clientName;
	public $tmstitle;
	public $tmsmessage;

	public $subClientName;
	public $subClientCode;
	public $viewDT;
	public $completeDT;
	public $verifyDT;
  /**
  * 현재 클래스의 변수에 json_decode 처리된 데이터 할당
  */
  public function fromJsonInfo($jsonInfo)
  {
    isset($jsonInfo->receiptID) ? $this->receiptID = $jsonInfo->receiptID : null;
    isset($jsonInfo->regDT) ? $this->regDT = $jsonInfo->regDT : null;
    isset($jsonInfo->state) ? $this->state = $jsonInfo->state : null;

    isset($jsonInfo->expires_in) ? $this->expires_in = $jsonInfo->expires_in : null;
    isset($jsonInfo->callCenterNum) ? $this->callCenterNum = $jsonInfo->callCenterNum : null;

    isset($jsonInfo->allowSimpleRegistYN) ? $this->allowSimpleRegistYN = $jsonInfo->allowSimpleRegistYN : null;
    isset($jsonInfo->verifyNameYN) ? $this->verifyNameYN = $jsonInfo->verifyNameYN : null;
    isset($jsonInfo->payload) ? $this->payload = $jsonInfo->payload : null;
    isset($jsonInfo->requestDT) ? $this->requestDT = $jsonInfo->requestDT : null;
    isset($jsonInfo->expireDT) ? $this->expireDT = $jsonInfo->expireDT : null;
    isset($jsonInfo->clientCode) ? $this->clientCode = $jsonInfo->clientCode : null;
    isset($jsonInfo->clientName) ? $this->clientName = $jsonInfo->clientName : null;
    isset($jsonInfo->tmstitle) ? $this->tmstitle = $jsonInfo->tmstitle : null;
    isset($jsonInfo->tmsmessage) ? $this->tmsmessage = $jsonInfo->tmsmessage : null;

    isset($jsonInfo->subClientName) ? $this->subClientName = $jsonInfo->subClientName : null;
    isset($jsonInfo->subClientCode) ? $this->subClientCode = $jsonInfo->subClientCode : null;
    isset($jsonInfo->viewDT) ? $this->viewDT = $jsonInfo->viewDT : null;
    isset($jsonInfo->completeDT) ? $this->completeDT = $jsonInfo->completeDT : null;
    isset($jsonInfo->verifyDT) ? $this->verifyDT = $jsonInfo->verifyDT : null;
  }
}


class RequestESign
{
  public $CallCenterNum;
	public $Expires_in;
	public $PayLoad;
	public $ReceiverBirthDay;
	public $ReceiverHP;
	public $ReceiverName;
	public $SubClientID;
	public $TMSMessage;
	public $TMSTitle;
	public $Token;
	public $isAllowSimpleRegistYN;
	public $isVerifyNameYN;
  public $isAppUseYN;
}


class ResultESign
{
  public $receiptID;
	public $regDT;
	public $state;

	public $expires_in;
	public $callCenterNum;

	public $allowSimpleRegistYN;
	public $verifyNameYN;
	public $payload;
	public $requestDT;
	public $expireDT;
	public $clientCode;
	public $clientName;
	public $tmstitle;
	public $tmsmessage;

	public $subClientName;
	public $subClientCode;
	public $viewDT;
	public $completeDT;
	public $verifyDT;
  public $appUseYN;
  public $tx_id;
  /**
  * 현재 클래스의 변수에 json_decode 처리된 데이터 할당
  */
  public function fromJsonInfo($jsonInfo)
  {
    isset($jsonInfo->receiptID) ? $this->receiptID = $jsonInfo->receiptID : null;
    isset($jsonInfo->regDT) ? $this->regDT = $jsonInfo->regDT : null;
    isset($jsonInfo->state) ? $this->state = $jsonInfo->state : null;

    isset($jsonInfo->expires_in) ? $this->expires_in = $jsonInfo->expires_in : null;
    isset($jsonInfo->callCenterNum) ? $this->callCenterNum = $jsonInfo->callCenterNum : null;

    isset($jsonInfo->allowSimpleRegistYN) ? $this->allowSimpleRegistYN = $jsonInfo->allowSimpleRegistYN : null;
    isset($jsonInfo->verifyNameYN) ? $this->verifyNameYN = $jsonInfo->verifyNameYN : null;
    isset($jsonInfo->payload) ? $this->payload = $jsonInfo->payload : null;
    isset($jsonInfo->requestDT) ? $this->requestDT = $jsonInfo->requestDT : null;
    isset($jsonInfo->expireDT) ? $this->expireDT = $jsonInfo->expireDT : null;
    isset($jsonInfo->clientCode) ? $this->clientCode = $jsonInfo->clientCode : null;
    isset($jsonInfo->clientName) ? $this->clientName = $jsonInfo->clientName : null;
    isset($jsonInfo->tmstitle) ? $this->tmstitle = $jsonInfo->tmstitle : null;
    isset($jsonInfo->tmsmessage) ? $this->tmsmessage = $jsonInfo->tmsmessage : null;

    isset($jsonInfo->subClientName) ? $this->subClientName = $jsonInfo->subClientName : null;
    isset($jsonInfo->subClientCode) ? $this->subClientCode = $jsonInfo->subClientCode : null;
    isset($jsonInfo->viewDT) ? $this->viewDT = $jsonInfo->viewDT : null;
    isset($jsonInfo->completeDT) ? $this->completeDT = $jsonInfo->completeDT : null;
    isset($jsonInfo->verifyDT) ? $this->verifyDT = $jsonInfo->verifyDT : null;
    isset($jsonInfo->appUseYN) ? $this->appUseYN = $jsonInfo->appUseYN : null;
    isset($jsonInfo->tx_id) ? $this->tx_id = $jsonInfo->tx_id : null;


  }
}

?>
