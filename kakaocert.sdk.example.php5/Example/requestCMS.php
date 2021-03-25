<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<link rel="stylesheet" type="text/css" href="/Example.css" media="screen" />
		<title>Kakaocert SDK PHP 5.X Example.</title>
	</head>
<?php

  /*
  * 자동이체 출금동의 인증을 요청합니다.
  * - 해당 서비스는 전자서명을 하는 당사자와 출금계좌의 예금주가 동일한 경우에만 사용이 가능합니다.
  * - 전자서명 당사자와 출금계좌의 예금주가 동일인임을 체크하는 의무는 이용기관에 있습니다.
  * - 금융결제원에 증빙자료(전자서명 데이터) 제출은 이용기관 측 에서 진행해야 합니다.
  */

  include 'common.php';

  // Kakaocert 이용기관코드, Kakaocert 파트너 사이트에서 확인
  $clientCode = '020040000001';

  // 자동이체 출금동의 요청정보 객체
  $RequestCMS = new RequestCMS();

  // 고객센터 전화번호, 카카오톡 인증메시지 중 "고객센터" 항목에 표시
  $RequestCMS->CallCenterNum = '1600-8536';

  // 인증요청 만료시간(초), 최대값 1000, 인증요청 만료시간(초) 내에 미인증시 만료 상태로 처리됨
  $RequestCMS->Expires_in = 60;

  // 수신자 생년월일, 형식 : YYYYMMDD
  $RequestCMS->ReceiverBirthDay = '19700101';

  // 수신자 휴대폰번호
  $RequestCMS->ReceiverHP = '010111222';

  // 수신자 성명
  $RequestCMS->ReceiverName = '테스트';

  // 예금주명
  $RequestCMS->BankAccountName = '예금주명';

  // 계좌번호, 이용기관은 사용자가 식별가능한 범위내에서 계좌번호의 일부를 마스킹 처리할 수 있음 (예시) 371-02-6***85
  $RequestCMS->BankAccountNum = '9-4324-5**7-58';

  // 은행코드
  $RequestCMS->BankCode = '004';

  // 납부자번호, 이용기관에서 부여한 고객식별번호
  $RequestCMS->ClientUserID = 'clientUserID-0423-01';

  // 별칭코드, 이용기관이 생성한 별칭코드 (파트너 사이트에서 확인가능)
  // 카카오톡 인증메시지 중 "요청기관" 항목에 표시
  // 별칭코드 미 기재시 이용기관의 이용기관명이 "요청기관" 항목에 표시
  $RequestCMS->SubClientID = '';

  // 인증요청 메시지 부가내용, 카카오톡 인증메시지 중 상단에 표시
  $RequestCMS->TMSMessage = 'TMSMessage0423';

  // 인증요청 메시지 제목, 카카오톡 인증메시지 중 "요청구분" 항목에 표시
  $RequestCMS->TMSTitle = 'TMSTitle 0423';

  // 은행계좌 실명확인 생략여부
  // true : 은행계좌 실명확인 절차를 생략
  // false : 은행계좌 실명확인 절차를 진행
  // 카카오톡 인증메시지를 수신한 사용자가 카카오인증 비회원일 경우, 카카오인증 회원등록 절차를 거쳐 은행계좌 실명확인 절차를 밟은 다음 전자서명 가능
  $RequestCMS->isAllowSimpleRegistYN = false;

  // 수신자 실명확인 여부
  // true : 카카오페이가 본인인증을 통해 확보한 사용자 실명과 ReceiverName 값을 비교
  // false : 카카오페이가 본인인증을 통해 확보한 사용자 실명과 RecevierName 값을 비교하지 않음.
  $RequestCMS->isVerifyNameYN = false;

  // PayLoad, 이용기관이 생성한 payload(메모) 값
  $RequestCMS->PayLoad = 'Payload123';

  try {
    $receiptID = $KakaocertService->requestCMS($clientCode, $RequestCMS);
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
				<legend>자동이체 출금동의 요청</legend>
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
