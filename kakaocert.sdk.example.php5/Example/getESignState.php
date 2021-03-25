<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<link rel="stylesheet" type="text/css" href="/Example.css" media="screen" />
		<title>Kakaocert SDK PHP 5.X Example.</title>
	</head>
<?php

  /*
  * 전자서명 요청에 대한 서명 상태를 확인합니다.
  */

  include 'common.php';

  // Kakaocert 이용기관코드, Kakaocert 파트너 사이트에서 확인
  $clientCode = '020040000001';

  // 전자서명 요청시 반환받은 접수아이디
  $receiptID = '020090816184100001';

  try {
    $result = $KakaocertService->getESignState($clientCode, $receiptID);
  }
  catch(KakaocertException $ke) {
    $code = $ke->getCode();
    $message = $ke->getMessage();
  }
?>
	<body>
		<div id="content">
			<p class="heading1">Response</p>
			<br/>
			<fieldset class="fieldset1">
				<legend>전자서명 서명상태 확인</legend>
				<ul>
					<?php
						if ( isset($code) ) {
					?>
							<li>Response.code : <?php echo $code ?> </li>
							<li>Response.message : <?php echo $message ?></li>
					<?php
						} else {
					?>
              <li>receiptID (접수 아이디) : <?php echo $result->receiptID ?></li>
              <li>tx_id (카카오톡 트랜잭션아이디-AppToApp용) : <?php echo $result->tx_id ?></li>
              <li>clientCode (이용기관코드) : <?php echo $result->clientCode ?></li>
              <li>clientName (이용기관명) : <?php echo $result->clientName ?></li>
              <li>state (상태코드) : <?php echo $result->state ?></li>
              <li>regDT (등록일시) : <?php echo $result->regDT ?></li>
              <li>expires_in (인증요청 만료시간(초)) : <?php echo $result->expires_in ?></li>
              <li>callCenterNum (고객센터 번호) : <?php echo $result->callCenterNum ?></li>

              <li>allowSimpleRegistYN (은행계좌 실명확인 생략여부	) : <?php echo $result->allowSimpleRegistYN ?></li>
              <li>verifyNameYN (수신자 실명확인 여부) : <?php echo $result->verifyNameYN ?></li>
              <li>payload (payload) : <?php echo $result->payload ?></li>
              <li>requestDT (카카오 인증서버 등록일시) : <?php echo $result->requestDT ?></li>
              <li>expireDT (인증요청 만료일시) : <?php echo $result->expireDT ?></li>
              <li>tmstitle (인증요청 메시지 제목) : <?php echo $result->tmstitle ?></li>
              <li>tmsmessage (인증요청 메시지 부가내용) : <?php echo $result->tmsmessage ?></li>

              <li>subClientName (별칭) : <?php echo $result->subClientName ?></li>
              <li>subClientCode (별칭코드) : <?php echo $result->subClientCode ?></li>
              <li>viewDT (수신자 카카오톡 인증메시지 확인일시) : <?php echo $result->viewDT ?></li>
              <li>completeDT (수신자 카카오톡 전자서명 완료일시	) : <?php echo $result->completeDT ?></li>
              <li>verifyDT (전자서명 검증일시) : <?php echo $result->verifyDT ?></li>
              <li>appUseYN (AppToApp 호출여부) : <?php echo $result->appUseYN ?></li>
					<?php
						}
					?>
				</ul>
			</fieldset>
		 </div>
	</body>
</html
