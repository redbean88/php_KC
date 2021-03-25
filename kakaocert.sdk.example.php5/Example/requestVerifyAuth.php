<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<link rel="stylesheet" type="text/css" href="/Example.css" media="screen" />
		<title>Kakaocert SDK PHP 5.X Example.</title>
	</head>
<?php

  /*
  * 본인인증 전자서명을 요청합니다.
  * - 본인인증 서비스에서 이용기관이 생성하는 Token은 사용자가 전자서명할 원문이 됩니다. 이는 보안을 위해 1회용으로 생성해야 합니다.
  * - 사용자는 이용기관이 생성한 1회용 토큰을 서명하고, 이용기관은 그 서명값을 검증함으로써 사용자에 대한 인증의 역할을 수행하게 됩니다.
  */
	include	'testUserInfo.php';
	$UserInfo = new UserInfo();
  include 'common.php';

  // Kakaocert 이용기관코드, Kakaocert 파트너 사이트에서 확인
  $clientCode = '020040000001';

  // 본인인증 요청정보 객체
  $RequestVerifyAuth = new RequestVerifyAuth();

  // 고객센터 전화번호, 카카오톡 인증메시지 중 "고객센터" 항목에 표시
  $RequestVerifyAuth->CallCenterNum = '1600-8536';

  // 인증요청 만료시간(초), 최대값 1000, 인증요청 만료시간(초) 내에 미인증시 만료 상태로 처리됨
  $RequestVerifyAuth->Expires_in = 60;

  // 수신자 생년월일, 형식 : YYYYMMDD
  $RequestVerifyAuth->ReceiverBirthDay = $UserInfo->birth;

  // 수신자 휴대폰번호
  $RequestVerifyAuth->ReceiverHP = $UserInfo->tel;

  // 수신자 성명
  $RequestVerifyAuth->ReceiverName = $UserInfo->name;

  // 별칭코드, 이용기관이 생성한 별칭코드 (파트너 사이트에서 확인가능)
  // 카카오톡 인증메시지 중 "요청기관" 항목에 표시
  // 별칭코드 미 기재시 이용기관의 이용기관명이 "요청기관" 항목에 표시
  $RequestVerifyAuth->SubClientID = '';

  // 인증요청 메시지 부가내용, 카카오톡 인증메시지 중 상단에 표시
  $RequestVerifyAuth->TMSMessage = 'TMSMessage0423';

  // 인증요청 메시지 제목, 카카오톡 인증메시지 중 "요청구분" 항목에 표시
  $RequestVerifyAuth->TMSTitle = 'TMSTitle 0423';

  // 토큰 원문
  $RequestVerifyAuth->Token = "TMS Token 0423 ";

  // 은행계좌 실명확인 생략여부
  // true : 은행계좌 실명확인 절차를 생략
  // false : 은행계좌 실명확인 절차를 진행
  // 카카오톡 인증메시지를 수신한 사용자가 카카오인증 비회원일 경우, 카카오인증 회원등록 절차를 거쳐 은행계좌 실명확인 절차를 밟은 다음 전자서명 가능
  $RequestVerifyAuth->isAllowSimpleRegistYN = false;

  // 수신자 실명확인 여부
  // true : 카카오페이가 본인인증을 통해 확보한 사용자 실명과 ReceiverName 값을 비교
  // false : 카카오페이가 본인인증을 통해 확보한 사용자 실명과 RecevierName 값을 비교하지 않음.
  $RequestVerifyAuth->isVerifyNameYN = false;

  // PayLoad, 이용기관이 생성한 payload(메모) 값
  $RequestVerifyAuth->PayLoad = 'Payload123';

  try {
		//KakaocertService는 common.php에서 인스턴스화 되어 있음
    $receiptID = $KakaocertService->requestVerifyAuth($clientCode, $RequestVerifyAuth);
  }
  catch(KakaocertException $pe) {
    $code = $pe->getCode();
    $message = $pe->getMessage();
  }
?>
	<body>
		<div id="content">
			<p class="heading1">Response</p>
			<br/>
			<fieldset class="fieldset1">
				<legend>본인인증 요청</legend>
				<ul>

          <?php
            if ( isset($receiptID) ) {
          ?>
            <li>접수아이디 (receiptID) : <?php echo $receiptID ?></li>
          <?php
            } else {
          ?>
            <li>Response.code : <?php echo $code ?> </li>
            <li>Response.message : <?php echo $message ?></li>
          <?php
            }
          ?>
				</ul>
			</fieldset>
		 </div>
	</body>
</html>
