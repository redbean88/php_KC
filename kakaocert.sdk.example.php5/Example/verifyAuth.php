<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<link rel="stylesheet" type="text/css" href="/Example.css" media="screen" />
		<title>Kakaocert SDK PHP 5.X Example.</title>
	</head>
<?php

  /*
  * 본인인증 요청건에 대해 서명을 검증합니다.
  * - 서명검증시 전자서명 데이터 전문(signedData)이 반환됩니다.
  * - 본인인증 요청시 작성한 Token과 서명 검증시 반환되는 signedData의 동일여부를 확인하여 본인인증 검증을 완료합니다.
  * - 카카오페이 서비스 운영정책에 따라 검증 API는 1회만 호출할 수 있습니다. 재시도시 오류처리됩니다.
  */

  include 'common.php';

  // Kakaocert 이용기관코드, Kakaocert 파트너 사이트에서 확인
  $clientCode = '020040000001';

  // 본인인증 요청시 반환받은 접수아이디
  $receiptID = '020090816110300001';

  try {
    $result = $KakaocertService->verifyAuth($clientCode, $receiptID);
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
				<legend>본인인증 서명 검증</legend>
				<ul>

          <?php
            if ( isset($result) ) {
          ?>
            <li>접수아이디 (receiptId) : <?php echo $result->receiptId ?></li>
            <li>전자서명 데이터 (signedData) : <?php echo $result->signedData ?></li>
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
