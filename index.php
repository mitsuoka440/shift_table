<?php
// 初期設定（XSS、クリックジャンクション、データベース
require_once (dirname(__FILE__).'/../Base/syoki_settei.php');


//法定休憩計算、給与計算関数を読み込み
require_once ('sub_f/keisan.php');
require_once ('sub_f/houteikyukei.php');

//　XSSエスケープ
$_POST=h($_POST);

//出力用の曜日を配列格納　datetimeに紐付けしてないので注意
$week = ['月','火','水','木','金','土','日'];
//色データoption用
$color_index = ["red1","blue1","purple1","green1","yellow1","red2","blue2","purple2","green2","yellow2"];
$color_name = ["赤1","青1","紫1","緑1","黄1","赤2","青2","紫2","緑2","黄2"];

//SESSIONのランク、IDを得る
if(isset($_SESSION['rank'])){
  $admin_log = h($_SESSION['rank']);
}else{
  $admin_log = "gest";
}

if(isset($_SESSION['ad_info'])){
  $ad_count = h($_SESSION['ad_info']);
}else{
  $ad_count = 0;
}

//POSTで入力データを得る

//出勤、退勤データ
for($i=0;$i<7;$i++){//1週間分ループ
  if(isset($_POST['in_'.$i])){
    ${'in_'.$i} =$_POST['in_'.$i];
  }else{
    ${'in_'.$i} = array();
  }
  if(isset($_POST['in_f'.$i])){
    ${'in_f'.$i} =$_POST['in_f'.$i];
  }else{
    ${'in_f'.$i} = array();
  }
  if(isset($_POST['out_'.$i])){
    ${'out_'.$i} =$_POST['out_'.$i];
  }else{
    ${'out_'.$i} = array();
  }
  if(isset($_POST['out_f'.$i])){
    ${'out_f'.$i} =$_POST['out_f'.$i];
  }else{
    ${'out_f'.$i} = array();
  }
}

//シフトデータ配列準備
$staff = $shiftcolor = $per_hour = array();

//スタッフ名
if(isset($_POST['staff'])){
  $staff =$_POST['staff'];
  foreach($staff as $key=>$s){//4文字以上はカット
    if(mb_strlen($s,"UTF-8")>5){
      $staff[$key] = mb_substr($s,0,4,"UTF-8");
    }
  }
}

//シフト色
if(isset($_POST['shiftcolor'])){
  $shiftcolor =$_POST['shiftcolor'];
}

//時給
if(isset($_POST['per_hour'])){
  $per_hour =$_POST['per_hour'];
  foreach($per_hour as $key=>$p){//5桁入力、数字以外の混入あれば０円
    if(mb_strlen($p,"UTF-8")>5 || !ctype_digit($p)){
      $per_hour[$key] = 0;
    }
  }
}

//休憩
$rest = "";
if(isset($_POST['rest'])){
  $rest = $_POST['rest'];
}

if(isset($_POST['site_no'])){//ボタンを押した判定
  $s_no =$_POST['site_no'];
}else{
  $s_no = 0;
}

if($admin_log !== '0'){//管理者以外の場合はSQLにつないでカウントアップ
  //MySQLデータベースに接続する
  try{
    $pdo = new PDO($dsn,$user,$password);
    //プリペアドステートメントのエミュレーションを無効にする
    $pdo -> setAttribute(PDO::ATTR_EMULATE_PREPARES,false);
    //例外がスローされる設定にする
    $pdo -> setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

    //whereを設定しないと同じ値ですべて更新されるので注意
    $sql = "UPDATE Site_status SET Run = Run + 1 WHERE id = :id ";
    // プリペアドステートメントを作る
    $stm = $pdo->prepare($sql);
    //プレースホルダに値をバインドする（　3：　シフト表の集計回数　）
    $stm->bindValue(':id',$s_no,PDO::PARAM_INT);
    // SQL文を実行する
    $stm->execute();
  }catch(Exception $e){
    echo'<spanclass="error">エラーがありました。</span><br>';
    echo$e->getMessage();
    exit();
  }
}


//スタッフ人数変更
$m_staff=60;//最大数はここで変更
if(isset($_POST['max_staff'])){
  $max_staff =intval($_POST['max_staff']);
  if($max_staff > $m_staff){//最大以上はカット
    $max_staff = $m_staff;
  }
}else{
  $max_staff = 10;
}

//sampleボタン
$sp = 0;
if(isset($_POST['sample'])){//スタッフ名
  $sp =$_POST['sample'];
}

//表示ボタン
$hyo = 0;
if(isset($_POST['hyo'])){//スタッフ名
  $hyo = $_POST['hyo'];
}

$max_size = 1536000;//最大ファイルサイズを定数定義 150KBにする　1KB=10240バイトなので
$csv=0;
$er_msg = $ex_name = array();//エラーメッセージは配列で保存 その他変数初期化
$name = $tmp_name = $size = $img_type = $ext = $save_file = $up_error ="";
if (isset($_FILES['csv'])) {//ファイルがアップロードされているか？　POSTすると空でもここにくるみたい
  //ファイル情報を得る
  $name     = $_FILES['csv']['name'];//ファイル名
  $tmp_name = $_FILES['csv']['tmp_name'];//仮保存先　ここからダイレクトで表示は無理だった
  $size     = $_FILES['csv']['size'];//ファイルサイズ
  $fl_type = $_FILES['csv']['type'];//MIMEタイプ　攻撃者は自分で変えることがある
  $up_error = $_FILES['csv']['error'];//エラーが有った場合に状態が入る

if($name !=""){
  //アップロードエラーを調査する。
  //エラー状態はswitchは使えない値なら使えるかも　PHPマニュアル参照
  if($up_error != UPLOAD_ERR_OK && $up_error != UPLOAD_ERR_NO_FILE){//エラー無しか空でなかったら
    if($up_error == UPLOAD_ERR_INI_SIZE || $up_error == UPLOAD_ERR_FORM_SIZE){//ファイルサイズエラー
        $er_msg[]="ファイルサイズが大きすぎます";
    }else{//その他エラー
        $er_msg[]="アップロードエラーがありました";
    }
  }

  //ファイルサイズ情報から規定サイズかどうか確認
  if($size > $max_size){
    $er_msg[]="ファイルサイズは150KB以下にしてください";
  }

  //MIMEタイプが実際のファイルと情報が一致するかチェックをかける
  if($tmp_name!=""){ //一時保存ファイルがあるときは処理
    $finfo = new finfo(FILEINFO_MIME_TYPE);//クラスを作成
    $finfoType = $finfo->file($tmp_name);//一時保持ファイルのタイプを調べる
    if($finfoType != $fl_type && $finfoType != 'text/plain'){
      $er_msg[]="ファイルのMIMEタイプが一致しません";
    }else{
      //タイプが一致していたら、MIMEタイプから拡張子を得る.拡張子が3つ以外なら排除
      if($fl_type=='text/csv'){
        $ext = 'csv';
      }else{
        $er_msg[]="アップロード可能なファイルはcsvのみです";
      }
    }

  //一旦保存用のファイル移動先名の作成
  $save_file = 'sample/' . time() . '_'
            . md5(microtime() . $name . $_SERVER['REMOTE_ADDR']) . '.' . $ext;

  }

//csvファイルのアップロード
if(move_uploaded_file($tmp_name,$save_file) && count($er_msg)==0){
  $csv=1;
}else{
  $er_msg[]="ファイルのアップロードに失敗しました";
}
}
}
//デモ用シフトデータの準備
if ($sp == 1 || $csv==1){
  //格納変数初期化
  for($i=0;$i<7;$i++){
      ${'in_'.$i} = array();
      ${'out_'.$i} = array();
  }
  //シフトデータ配列準備
  $staff = $shiftcolor = $per_hour = array();

  //CSVフィアルを読み込み
  if($sp==1){
    $filename = "sample/shift2.csv";
  }
  if($csv==1){
    $filename = $save_file;
  }

  $filedata = file_get_contents($filename);
  //一時ファイルを作って保存
  $fp = tmpfile();
  fwrite($fp, $filedata);
  // ファイルポインタを先頭にする
  rewind($fp);
  // ローケルを設定
  setlocale(LC_ALL, 'ja_JP.UTF-8');

  $firsttime = 0;

  while ($arr = fgetcsv($fp)) {
    if (! array_diff($arr, array(''))) { //空行を除外
      continue;
    }
    //CVSカラムの項目　業務は整形してすべて読み込む
    //店番	名前	時給	色	月	月	火	火	水	水	木	木	金	金	土	土	日	日
    //読み込んだファイルデータの数調べて一致しなかったら終了
    if(count($arr) != 32){
      $er_msg[]="ファイルのデータの形が合いません。";
      break;
    }

    list( $sh_sno,$sh_na,$sh_phour,$sh_color,
    $sh_in0,$shf_in0,$sh_out0,$shf_out0,
    $sh_in1,$shf_in1,$sh_out1,$shf_out1,
    $sh_in2,$shf_in2,$sh_out2,$shf_out2,
    $sh_in3,$shf_in3,$sh_out3,$shf_out3,
    $sh_in4,$shf_in4,$sh_out4,$shf_out4,
    $sh_in5,$shf_in5,$sh_out5,$shf_out5,
    $sh_in6,$shf_in6,$sh_out6,$shf_out6) = $arr;


    if($firsttime != 0 ){//1行目は省く
      //名前は4文字以下か？
      if(mb_strlen($sh_na,"UTF-8")>5){
        $er_msg[]="ファイルのデータの形が合いません。";
        break;
      }
      //時給は4桁以内か？
      if(mb_strlen($sh_phour,"UTF-8")>5){
        $er_msg[]="ファイルのデータの形が合いません。";
        break;
      }else if(! ctype_digit($sh_phour)){//全部数字か？
        $er_msg[]="ファイルのデータの形が合いません。";
        break;
      }
      //色は指定の文字列と一緒か？
      $c_ck=0;
      foreach($color_index as $c){
        if($c==$sh_color){
          $c_ck = 1;
        }
      }
      if($c_ck==0){
        $er_msg[]="ファイルのデータの形が合いません。";
        break;
      }
      //勤務時間は空白OK、０から２４以内の数字はOK
      $time_ck=0;
      for($i=0;$i<7;$i++){
        if(${'sh_in'.$i}!=''){//空白はシフトなしなのでスルー
          if(!ctype_digit(${'sh_in'.$i})){
            $time_ck=1;
            break;
          }else if(${'sh_in'.$i}>24 && ${'sh_in'.$i}<0){
            $time_ck=1;
            break;
          }
        }
        if(${'sh_out'.$i}!=''){//空白はシフトなしなのでスルー
          if(!ctype_digit(${'sh_out'.$i})){
            $time_ck=1;
            break;
          }else if(${'sh_out'.$i}>24 && ${'sh_out'.$i}<0){
            $time_ck=1;
            break;
          }
        }
      }
      $time_m_ck=0;
      for($i=0;$i<7;$i++){
        if(${'shf_in'.$i}!=''){//空白はシフトなしなのでスルー
          if(${'shf_in'.$i}!='0' && ${'shf_in'.$i}!='15' && ${'shf_in'.$i}!='30' && ${'shf_in'.$i}!='45'){//空白はシフトなしなのでスルー
            $time_m_ck=1;
            break;
          }
        }
        if(${'shf_out'.$i}!=''){//空白はシフトなしなのでスルー
          if(${'shf_out'.$i}!='0' && ${'shf_out'.$i}!='15' && ${'shf_out'.$i}!='30' && ${'shf_out'.$i}!='45'){//空白はシフトなしなのでスルー
            $time_m_ck=1;
            break;
          }
        }
      }

      if($time_ck==1){
        $er_msg[]="ファイルのデータの時間の形が合いません。";
        break;
      }
      if($time_m_ck==1){
        $er_msg[]="ファイルのデータの分の形が合いません。";
        break;
      }

      $staff[]      = h($sh_na);
      $per_hour[]   = h($sh_phour);
      $shiftcolor[] = h($sh_color);

      for($i=0;$i<7;$i++){
        ${'in_'.$i}[]  = h(${'sh_in'.$i});
        ${'out_'.$i}[] = h(${'sh_out'.$i});
        ${'in_f'.$i}[]  = h(${'shf_in'.$i});
        ${'out_f'.$i}[] = h(${'shf_out'.$i});
      }

    }
    $firsttime = 1;
  }

  //ファイルロックを解除
  fflush($fp);
  flock($fp, LOCK_UN);
  //ファイルを閉じる
  fclose($fp);
}//===================シフト読み込みデータここまで

//読み込みファイルがあるときは削除　serverファイル容量確保のため。すぐ消す
if($save_file!=""){
  unlink($save_file);
}

//最大入力人数がサンプルより多いのときは空白を入れておく
if($max_staff > count($staff)){
  $m=$max_staff-count($staff);
  for($i=0;$i<$m;$i++){
    $staff[]      = "";
    $per_hour[]   = "";
    $shiftcolor[] = "";
    for($j=0;$j<7;$j++){
      ${'in_'.$j}[]  = "";
      ${'out_'.$j}[] = "";
      ${'in_f'.$j}[]  = "";
      ${'out_f'.$j}[] = "";
    }
  }
}else if(count($staff) > $max_staff){//インポートデータがマックスより多いときはデータの数まで増やす
  $max_staff = count($staff);
}
//シフト表のデータを整形する。先に6時を０とした0.25刻みのデータを作ってしまったほうが早いかも
if(count($staff)!=0){

  for($i=0;$i<7;$i++){//初期化
    ${'si'.$i} = ${'so'.$i} = array();
  }

  foreach($staff as $key=>$s){
    for($i=0;$i<7;$i++){
      if(${'in_'.$i}[$key] != "" && ${'out_'.$i}[$key] != ""
          && ${'in_f'.$i}[$key]  != "" &&${'out_f'.$i}[$key] != ""){
        //出勤も退勤も0時から6時未満までは24を足す
        if(${'in_'.$i}[$key]<6){
          ${'si'.$i}[$key] = (${'in_'.$i}[$key]+24+(${'in_f'.$i}[$key]/60)-6)/0.25;
        }else{
          ${'si'.$i}[$key] = (${'in_'.$i}[$key]+(${'in_f'.$i}[$key]/60)-6)/0.25;
        }
        if(${'out_'.$i}[$key]<6){
          ${'so'.$i}[$key] = (${'out_'.$i}[$key]+24+(${'out_f'.$i}[$key]/60)-6)/0.25;
        }else{
          ${'so'.$i}[$key] = (${'out_'.$i}[$key]+(${'out_f'.$i}[$key]/60)-6)/0.25;
        }
        //さらに退勤時間が出勤時間より小さいときは、退勤時間に96（24÷0.25）を足す
        if(${'so'.$i}[$key] < ${'si'.$i}[$key]){
          ${'so'.$i}[$key] += 96;
        }
      }else{
        ${'si'.$i}[$key] = ${'so'.$i}[$key] = "";
      }
    }
  }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <mata charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <meta http-equiv="content-type" content="text/html;charset=shift_JIS">
  <meta http-equiv="Content-Style-Type" content="text/css">
  <meta http-equiv="Content-Script-Type" content="text/javascript">
  <meta content="24時間コンビニの基本シフト表を作成" name="description">
<title>シフト表作成ページ</title>
<link rel="stylesheet" type="text/css"
href="css/shiftstyle.css?<?php echo date('Ymd-Hi');?>">
</head>

<body>

<header class="menuheder">
  <h1>シフト表作成</h1>
  <p>（週間固定シフト表示）</p>
</header>

<div class="content">

<div class="respo_max">

<div class="main">
<div class="Explanation n_font">
  【ページの説明】<br>
  <div class="left_text">
  朝6時〜翌朝6時表記のシフト表を15分単位で作成するページです。<br>
  下のフォームに入力した情報でシフト表を作成します。<br>
  <br>
  </div>
  <a href="javascript:viod(0);" class="kaisetu" id="open0">【フォーム・各操作パネル説明】</a>
  <div id="setumei0" class="hide">
  <div class="left_text">
  ◆入力規制：<br>
  &emsp;表示名4文字、時給4桁まで、スタッフ登録人数10から60名です。<br><br>
  ◆<span class="sb_dummy">Sample</span>を押すとサンプル情報が20人分自動入力されます。<br>
  &emsp;自動入力情報は、後で追加・変更ができます。<br><br>
  ◆数字変更後、<span class="sb_dummy">人数変更</span>を押すと人数の変更ができます。<br>
  &emsp;ただし、先に入力した情報は消えます。ご注意ください。<br><br>
  ◆csvデータをアップロードできるようにしました。<br>
  &emsp;データの形は<span class="sb_dummy">ファイル仕様</span>を押してご確認ください。<br>
  &emsp;csvデータをアップロードした場合、人数制限は無しです。<br><br>
  ◆データ入力、もしくはアップロード後、<span class="b_dummy">表示</span>を押してください。<br>
  <br>
  ◆<span class="b_dummy">PDF出力</span>を押すと、作成したシフト表を別タブでPDF出力します。<br>
  &emsp;用紙の向きとサイズを選択し<span class="sb_dummy">既定値表示</span>を押すと<br>
  &emsp;目安のセルの設定値(縦、横、文字の大きさ）が表示されます。<br>
  &emsp;微調整は出力しながらお願いします。<br>
  &emsp;（注意：セルの横幅は小数点単位で大きく変わります。）<br>
  </div>
</div>
</div>
</div>

<div class="side">
  <div class="group">
  <p class="subheading">【入力補助操作パネル】</p><br>
  <form method="POST" action="<?php echo h($_SERVER['SCRIPT_NAME']);?>">
    <div class="left_text">
      ◆スタッフ人数変更&emsp;
    <input type="number" min="10" max="60" step="1" value="<?php echo $max_staff; ?>" name="max_staff">
    人
    <button type="submit" class="samplebutton">人数変更</button>
  </div>
    <br>
  </form>
  <form method="POST" action="<?php echo h($_SERVER['SCRIPT_NAME']);?>#result2">
    <input type="hidden" value="<?php echo $max_staff; ?>" name="max_staff">
    <div class="left_text">
    ◆サンプルデータ呼び出し&emsp;
    <button type="submit" value="1" class="samplebutton" name="sample">Sample</button>
  </div>
  </form>

<form method="POST" action="<?php echo h($_SERVER['SCRIPT_NAME']);?>#result1" enctype="multipart/form-data">
    <input type="hidden" name="max_staff" value="<?php echo $max_staff; ?>">
    <br>
    <div class="left_text">
    ◆概算人件費は休憩考慮する　<input type="checkbox" value="1" name='rest'><br>
    <br>
    ◆CSVファイルの読込み<br>
    &emsp;<input type="file" name="csv" class="n_font"><br><br>
  </div>
  <div class="box_parent left_text">
    ※CVSファイルデータの仕様
    <button type="button" id="open4" class="samplebutton">ファイル仕様</button>


    <div id="dialog3">
    <span class="subheading">【CSVファイルの形式について】</span>
    <button type="button" id="close3">閉じる</button>

    <span class="kaisetu_left">
    <dl>
      <dt>CSVファイルの容量</dt>
      <dd>150KBまで</dd>
        <br>
      <dt>ファイル形式</dt>
      <dd>拡張子がcsv（text/plainのcsvはOK）</dd>
        <br>
      <dt>データ形式について</dt>
      <dd> 行の形式（32データ）<br>
        No,名前,時給,シフト色,「出勤時間,出勤分,退勤時間,退勤分」を月〜日まで繰返して改行<br>
        名前は４文字まで、時給の数字はカンマなし４桁までにしてください。<br>
        出勤、退勤の時間は、24時間表記で「時間だけ」を半角数字(6や10など)、分は0,15,30,45の半角数字にし、
        勤務がない場合は,,で空データを作ってください。０を入れると０時と判定します。通常と同じ用に24時は存在しなく、0時判定です。<br>
        また、時間だけ、分だけとどちらかしか数字が入ってない場合はエラーを返します。<br>
        色は、<br>
        「red1,blue1,purple1,green1,yellow1,red2,blue2,purple2,green2,yellow2」<br>
        の10色で、表記はこのとおりに半角アルファベット数字で入れてください。<br>
        登録人数が多くなると、用紙の調整が必要になります。ライブラリの改ページ機能はオフにしてますので、上手に調整してください。
        需要があれば2枚出しに変更できるようにします。<br>
        また、データの1行目（項目）と1列目（No)はつかいません。ご自身の管理用に使ってください。<br>
        （注意：データの形が少しでも違うとエラーになり表示されません。）<br>
        <br>
    </dd>
    </dl>
    </span>
    </div>

  </div>

    <br>
</div>
  <input type="hidden" name="site_no" value="3">
</div>
</div>

<p class="sheet_title">スタッフデータ入力フォーム
  <button type="submit" value="1" class="button" name="hyo">表示</button>
</p>
<table class="p_spread" id="result2">
  <tr>
    <th rowspan="3" class="key_cell">表示名</th>
    <th rowspan="3" class="top_header">時給</th>
    <th rowspan="3" class="top_header">シフト色</th>
    <?php
    for($i=0;$i<7;$i++){
      echo '<th class="top_header" colspan="4">',$week[$i],'</th>';
    }
    ?>
  </tr>
  <tr>
    <?php for($i=0;$i<7;$i++){
    echo '<th class="top_header2" colspan="2">開始</th><th class="top_header2" colspan="2">終了</th>';
    }?>
  </tr>
  <tr>
    <?php
    for($i=0;$i<7;$i++){
       for($j=0;$j<2;$j++){
    echo '<th class="top_header3">時</th><th class="top_header3">分</th>';
        }
    }?>
  </tr>
  <?php
  for($i=0;$i<$max_staff;$i++){  ?>
  <tr>
    <td class="left_header"><input type='text' name='staff[]' class="shift" maxlength="4" value="<?php echo $staff[$i] ;?>"></td>
    <td><input type='numbar' name='per_hour[]' class="per_hour" maxlength="4" value="<?php echo $per_hour[$i] ;?>"></td>
    <td>
      <select name='shiftcolor[]' class="ta_time_val">
        <option></option>
        <?php
        for($c=0;$c<10;$c++){
          $re="";
          if($color_index[$c] == $shiftcolor[$i]){
            $re = " selected";
          }
          echo '<option value="',$color_index[$c],'"',$re,'>',$color_name[$c],'</option>';
        }
        ?>
      </select>
    </td>
    <?php for($w=0;$w<7;$w++){?>
    <td>
      <select name=<?php echo "in_".$w."[]";?> class="ta_time_val">
        <option></option>
        <?php
         for ($j=0;$j<24;$j++){
           $re="";
           if($j == ${'in_'.$w}[$i] && ${'in_'.$w}[$i]!=""){
             $re = " selected";
           }
            echo '<option value="',$j,'"',$re,'>', $j,"</option>";
         }
         ?>
      </select>
    </td>
    <td>
      <select name=<?php echo "in_f".$w."[]";?> class="ta_time_val">
        <option></option>
        <?php
          for($t=0;$t<=45;$t+=15){
           $re="";
           if($t == ${'in_f'.$w}[$i] && ${'in_f'.$w}[$i]!=""){
             $re = " selected";
           }
            echo '<option value="',$t,'"',$re,'>', $t,"</option>";
         }
         ?>
      </select>
    </td>
    <td>
      <select name=<?php echo "out_".$w."[]";?> class="ta_time_val">
        <option></option>
        <?php
         for ($j=0;$j<24;$j++){
           $re="";
           if($j == ${'out_'.$w}[$i] && ${'out_'.$w}[$i]!=""){
             $re = " selected";
           }
           echo '<option value="',$j,'"',$re,'>', $j,"</option>" ;
         }
         ?>
      </select>
    </td>
    <td>
      <select name=<?php echo "out_f".$w."[]";?> class="ta_time_val">
        <option></option>
        <?php
          for($t=0;$t<=45;$t+=15){
           $re="";
           if($t == ${'out_f'.$w}[$i] && ${'out_f'.$w}[$i]!=""){
             $re = " selected";
           }
            echo '<option value="',$t,'"',$re,'>', $t,"</option>";
         }
         ?>
      </select>
    </td>
<?php } ?>
  </tr>
<?php } ?>
</table>
</form>


<br>
<?php
if($hyo==1){

if(count($er_msg)!=0 ){//エラーメッセージがあればPDFは表示させない
echo "csvファイルのアップロードについて、以下のエラーがありました<br><br>";
  foreach ($er_msg as $msg){
    echo "◆","&emsp;",h($msg),"<br>";
  }
echo "<br>";
}else{


//入力されたデータをSQLから読み込むように表形式の連想配列で曜日ごとに読み込む
$data = $yesterday = array();//配列変数初期化

//月曜シフト作成用に日曜夜勤の繰越を先に配列に入れ込む 変数名数字は６
if(count($si6)!= 0){
  for($i=0;$i<count($si6);$i++){
    if($so6[$i] > 96){
      $yesterday[] = array('name' => $staff[$i],'in' => 0,'out' => $so6[$i]-96,'shiftcolor' => $shiftcolor[$i],);
    }
  }
}

$s_fl=0;//空白以外のスタッフデータがあるかどうか確認
foreach($staff as $s){
 if($s!=""){$s_fl=1;}
}

if($s_fl==1){//データがあれば表示
echo '<p class="sheet_title" id="result1">基本シフト表</p>';
echo '<table class="white" >';

//１週間ループ開始　７回
for($w=0;$w<7;$w++){

//入力データをDatabeseの形に整形（MySQLから取る場合はこれは不要）
  if(count(${'si'.$w})!=0){
    for($i=0;$i<count(${'si'.$w});$i++){
      if($staff[$i]!== "" && ${'si'.$w}[$i]!== "" && ${'so'.$w}[$i]!== "" && ${'si'.$w}[$i] !== ${'so'.$w}[$i]){
        if($shiftcolor[$i]==""){
          $c_color="shift";
        }else{
          $c_color=$shiftcolor[$i];
        }
        $data[] = array('name' => $staff[$i],'in' => ${'si'.$w}[$i],'out' => ${'so'.$w}[$i],'shiftcolor' => $c_color,);
      }
    }
    if(count($yesterday)!=0){
      foreach($yesterday as $yd){
        $data[] = array('name' => $yd['name'],'in'=> $yd['in'],'out'=> $yd['out'],'shiftcolor' => $yd['shiftcolor'],);
      }
      $yesterday = array();//前日の繰越クリア
    }
  }
//曜日シフトデータを並び替えする
$data_in_sort = array();
foreach($data as $key => $d){
  $data_in_sort[$key] = $d['in'];
}
array_multisort($data_in_sort,SORT_ASC,$data);


//曜日ごとのデータをシフト1行ずつに整形しながらカウント
  $gyo = 0;//行番号
  do{
    ${'result_shift'.$gyo}=array();//行のスタッフデータを格納する配列

    $min=99;//最小の時間を探す　inの時間が入る
    $out="";//
    $hantei=0;//ここはループのたびにoutが入る（次の始まり）
    foreach($data as $key => $m_shift){
      if ($m_shift['in'] >= $hantei){
        //退勤が6時以上のシフトはカットして翌日に回す
        if($m_shift['out']>96){
          $out=96;
          //繰越用のシフトに入れる
          $yesterday[] = array('name' => $m_shift['name'],'in' => 0,'out' => $m_shift['out']-96,'shiftcolor' => $m_shift['shiftcolor'],);
        }else{
          $out=$m_shift['out'];
        }
        //ループ判定用
        $hantei = $out;
        //表示用配列に入れる（可変変数）
        ${'result_shift'.$gyo}[]=array(
          'name' => $m_shift['name'],
          'in' => $m_shift['in'],
          'out' => $out,
          'shiftcolor' => $m_shift['shiftcolor'],);

        unset($data[$key]);
      }
    }
    $gyo ++;
    //データがなくなったか配列のカウントをさせて判断
    $ck_cnt = count($data);
  }while($ck_cnt != 0);

//==============================ここから表示させる

  //テーブルのタイトル行
  echo '<tr><th class="shift1">時間</th>';
  for ($i=6;$i<=29;$i++){
    if($i>=24){
      echo '<th class="shift2" colspan="4">',$i-24,'</th>';
    }else{
      echo '<th class="shift2" colspan="4">',$i,'</th>';
    }
  }
  echo '</tr><tr>';
  echo '<th rowspan="',$gyo,'" class="shift1">',$week[$w],'曜日</th>';
  //テーブルのタイトル行終わり

for($g=0;$g<$gyo;$g++){
  for($i=0;$i<96;$i++){
    foreach(${'result_shift'.$g} as $cc){
      if($cc['in'] == $i){
        //４文字の表示名で4マスだったら　フォントを小さくする
        if(mb_strlen($cc['name'],"UTF-8")==4 && ($cc['out'] - $cc['in'])<=4){
          $font_f = '<span class="ss_font">';
          $font_e = '</span>';
        }else {
          $font_f ="";
          $font_e ="";
        }

        if(($cc['out']-$cc['in'])!=1){
          $h_col= 'colspan="'.($cc['out'] - $cc['in']).'"';
          echo '<td ',$h_col,' class="',$cc['shiftcolor'],'">',$font_f,$cc['name'],$font_e,"</td>";
        }else{//シフト幅が１のときは外に出す
          echo '<td class="',$cc['shiftcolor'],' shift_popup_parent"><div class="shift_popup">',$cc['name'],'</div></td>';
        }
        $i=$cc['out'];
      }
    }
    if($i<96){
      echo '<td class="sukima"></td>';
    }
  }
  echo "</tr>";
}

}//7回繰り返し

echo '</table>';
//表示ここまで
}
//配列をカンマ区切りにしてPOSTしてPDF作成する
$s_fl=0;//スタッフデータがあるかどうか確認
foreach($staff as $s){
 if($s!=""){$s_fl=1;}
}
if($s_fl==1){//データがあれば表示
  //最初のデータはそのまま入れる
  $staff_d = $staff[0];
  $shiftcolor_d = $shiftcolor[0];
  for($i=0;$i<7;$i++){
    ${'in_d_'.$i} =${'si'.$i}[0];
    ${'out_d_'.$i} =${'so'.$i}[0];
  }
  //2番めのデータからカンマ区切で追加
  for($j=1;$j<count($staff);$j++){
    $staff_d .= ",".$staff[$j];
    $shiftcolor_d .= ",".$shiftcolor[$j];
    for($i=0;$i<7;$i++){
      ${'in_d_'.$i} .= ",".${'si'.$i}[$j];
      ${'out_d_'.$i} .= ",".${'so'.$i}[$j];
    }
  }
  //POSTで入力データを得る
?>
<form method="POST" target="_blank" rel="noopener noreferrer" action="shift_pdf.php">
  <input type="hidden" name="staff" value="<?php echo $staff_d ;?>">
  <input type="hidden" name="shiftcolor" value="<?php echo $shiftcolor_d ;?>">
  <?php
  for($i=0;$i<7;$i++){
    echo '<input type="hidden" name="in',$i,'" value="',${'in_d_'.$i},'">';
    echo '<input type="hidden" name="out',$i,'" value="',${'out_d_'.$i},'">';
  }
  ?>
  <br>
  <div class="group2 n_font">
    【PDF作成パネル】
    <br><br>
    <div class="left_text">◆用紙の選択</div>
    サイズ<select name="paper_size" id="paper_s">
        <option value="A4" selected>A4</option>
        <option value="A3">A3</option>
    </select>
    &emsp;向き<select name="paper" id="paper">
        <option value="L" selected>横</option>
        <option value="P">縦</option>
    </select>
    &emsp;<button type="button" class="samplebutton" onclick="cellsize()">既定値表示</button>
    <br><br>
    <div class="left_text">◆セルの設定</div>
  縦幅&nbsp;<input type="number" name="shift_h" min="1" max="15" step="0.1" value="5" id="cell_h">
  横幅&nbsp;<input type="number" name="shift_w" min="1" max="12" step="0.1" value="2.1" id="cell_w">
  文字&nbsp;<input type="number" name="cell_font" min="1" max="15" step="0.1" value="6" id="cell_f">
  <br><br>
<button type="submit" name="s_data" class="button" value="">PDF出力</button>
<br><br>
</div>

</form>
<?php
}

//データを表示させながら給与計算させようと考えたが、
//同姓同名でも別々に計算させる方法を取るため別に集計することにする
//Databaseで運用するならidつけて分ければ同時進行も可能だと思う
//休憩時間に考慮のチェックがない場合は休憩計算しない

$staff_salary_f = $staff_salary_z = $staff_total = array_fill(0,$max_staff,0);
$staff_weektotal = $staffweek_over = array_fill(0,$max_staff,0);
$week_salary_f = $week_salary_z = $week_total = array_fill(0,7,0);

for($w=0;$w<7;$w++){//週で変数を分けているので7回ループ
  if(count(${'in_'.$w})!=0){
    for($i=0;$i<$max_staff;$i++){//$max_staffで人数管理
      if(${'in_'.$w}[$i] != "" && ${'out_'.$w}[$i] != "" && ${'in_'.$w}[$i] != ${'out_'.$w}[$i]){

      $salary_f = $salary_z = 0;
      $in_h = $out_h = $futuu = $zangyou = $sinya = $sinyazan = "";//初期化

      //時間に成形して変数に入れる（給与計算関数を使う）
      $in_h = sprintf("%02d",${'in_'.$w}[$i]).":".sprintf("%02d",${'in_f'.$w}[$i]);
      $out_h = sprintf("%02d",${'out_'.$w}[$i]).":".sprintf("%02d",${'out_f'.$w}[$i]);
        //休憩にチェックが入っていたら法定休憩を計算させる
        if ($in_h != "" && $out_h != "" && $rest == '1'){
          $kyukei = Houteikyukei($in_h,$out_h,"","");
        }else{
          $kyukei = "";
        }
      $kekka = array();
      $kekka = keisan($in_h,$out_h,$kyukei,"","");
      list($futuu,$zangyou,$sinya,$sinyazan)=$kekka;

      //すべて時間に換算
      $fj = intval(date("H",strtotime($futuu)));
      $ff = intval(date("i",strtotime($futuu)));
      $futuu = $fj+$ff/60;
      $zj = intval(date("H",strtotime($zangyou)));
      $zf = intval(date("i",strtotime($zangyou)));
      $zangyou = $zj+$zf/60;
      $sj = intval(date("H",strtotime($sinya)));
      $sf = intval(date("i",strtotime($sinya)));
      $sinya = $sj+$sf/60;
      $szj = intval(date("H",strtotime($sinyazan)));
      $szf = intval(date("i",strtotime($sinyazan)));
      $sinyazan = $szj+$szf/60;

      //$salary = ceil($futuu * (int)$per_hour[$i])+ceil(($zangyou+$sinya)*(int)$per_hour[$i]*1.25)+ceil($sinyazan*(int)$per_hour[$i]*1.5);
      $salary_f = ceil($futuu * (int)$per_hour[$i])+ceil($sinya*(int)$per_hour[$i]*1.25);
      $salary_z = ceil($zangyou * (int)$per_hour[$i]*1.25)+ceil($sinyazan*(int)$per_hour[$i]*1.5);
      $staff_salary_f[$i]=$staff_salary_f[$i] + $salary_f;//スタッフの通常給与
      $staff_salary_z[$i]=$staff_salary_z[$i] + $salary_z;//スタッフの残業給与
      $staff_total[$i]=$staff_total[$i] + $salary_f + $salary_z;//スタッフの合計給与
      $staff_weektotal[$i] = $staff_weektotal[$i] + $futuu + $sinya;//スタッフの通常時間の週合計
      $week_salary_f[$w] = $week_salary_f[$w] + $salary_f;//1日の通常給与
      $week_salary_z[$w] = $week_salary_z[$w] + $salary_z;//1日の残業給与
      $week_total[$w] = $week_total[$w] + $salary_f + $salary_z;//1日の合計給与
    }
  }
 }
}

if(array_sum($staff_total)!=0){

  $weeksalary_f_total = $weeksalary_z_total = 0;
  ?>
  <br><br><table>
    <caption>
      概算給与&nbsp;日別&nbsp;(単位:円)
    </caption>
    <tr>
      <th>曜日</th>
<?php
  for($w=0;$w<7;$w++){
    echo "<th>",$week[$w],"曜日</th>";
  }?>
    <th>週40超過分</th>
    <th>1週間合計</th>
    <th>平均(１日)</th>
    <th>月間(概算)</th>
    </tr>

    <tr>
      <th>通常給与</th>
<?php
    for($w=0;$w<7;$w++){
      echo '<td class="number">',number_format($week_salary_f[$w]),"</td>";
      $weeksalary_f_total = $weeksalary_f_total + $week_salary_f[$w];
    }
    echo '<td>-</td>';
    echo '<td class="number">',number_format($weeksalary_f_total),"</td>";
    echo '<td class="number">',number_format($weeksalary_f_total / 7),"</td>";
    echo '<td class="number">',number_format($weeksalary_f_total * 4.3),"</td>";
  ?>
</tr>
<tr>
  <th>残業給与</th>
  <?php
      for($w=0;$w<7;$w++){
        echo '<td class="number">',number_format($week_salary_z[$w]),"</td>";
        $weeksalary_z_total = $weeksalary_z_total + $week_salary_z[$w];
        $week_over = 0;
        for($i=0;$i<10;$i++){
          if($staff_weektotal[$i]>40){
            $week_over = $week_over + ($staff_weektotal[$i]-40)*(int)$per_hour[$i]*0.25;
          }
        }
      }
      echo '<td class="number">',number_format($week_over),"</td>";
      $weeksalary_z_total = $weeksalary_z_total + $week_over;
      echo '<td class="number">',number_format($weeksalary_z_total),"</td>";
      echo '<td class="number">',number_format($weeksalary_z_total / 7),"</td>";
      echo '<td class="number">',number_format($weeksalary_z_total * 4.35),"</td>";
    ?>
</tr>
<tr>
  <th>合計</th>
  <?php
      for($w=0;$w<7;$w++){
        echo '<td class="number">',number_format($week_total[$w]),"</td>";
      }
      echo '<td class="number">',number_format($week_over),"</td>";
      echo '<td class="number">',number_format($weeksalary_f_total + $weeksalary_z_total),"</td>";
      echo '<td class="number">',number_format(($weeksalary_f_total + $weeksalary_z_total)/ 7),"</td>";
      echo '<td class="number">',number_format(($weeksalary_f_total + $weeksalary_z_total)* 4.35),"</td>";
    ?>
</tr>
</table>

<table>
  <caption>
    概算給与&nbsp;スタッフ別&nbsp;(単位:円)
  </caption>
  <tr>
    <th>表示名</th>
    <th>通常給与</th>
    <th>残業給与</th>
    <th>週残業給与</th>
    <th>週合計給与</th>
    <th>月間概算</th>
    <th>年間概算</th>
  </tr>
<?php
  for($i=0;$i<$max_staff;$i++){
    if($staff[$i]!=""){
    echo "<tr>";
    echo "<th>",$staff[$i],"さん</th>";
    echo '<td class="number">',number_format($staff_salary_f[$i]),"</td>";
    echo '<td class="number">',number_format($staff_salary_z[$i]),"</td>";
    if($staff_weektotal[$i]>40){
       $staffweek_over[$i] = ($staff_weektotal[$i]-40)*(int)$per_hour[$i]*0.25;
     }else{
       $staffweek_over[$i] = 0;
     }
    echo '<td class="number">',number_format($staffweek_over[$i]),"</td>";
    echo '<td class="number">',number_format($staff_total[$i]+$staffweek_over[$i]),"</td>";
    echo '<td class="number">',number_format(($staff_total[$i]+$staffweek_over[$i])*4.35),"</td>";
    echo '<td class="number">',number_format(($staff_total[$i]+$staffweek_over[$i])*4.35*12),"</td>";
    echo "</tr>";
  }
  }?>
</table>
<?php
}
echo '<div class="center"><button id="open1">解説</button></div>';
}
}
?>
<a href="../s_manage/index.php">webツールサンプル集トップへ</a>

<div id="dialog1">
<p class="subheading">【シフト表の解説】</p>
<button id="close1">閉じる</button>
<button id="open2">仕様について</button>

<span class="kaisetu_left">
<dl>
  <dt>表示の工夫</dt>
  <dd>データがどの順番でも、表の隙間を上から順に埋めて表示し、同じ時間で勤務が重なっても行数は自動で増え、
    深夜勤務者が翌日6時以降で勤務した場合、翌日に表示されます。日曜日の深夜勤務者の場合、月曜に反映されます。
    ただし、24時間以上のシフトは対応していません。必要なら実装方法はありますが、スタッフ個別管理が必要です。</dd>
    <br>
  <dt>時間単位について</dt>
  <dd>1時間単位でサンプル表示してましたが、15分単位表示に変更しました。1分でも理論上は可能ですが表示に工夫は必要です。
  また、1時間4単位に分割しての作成なので、PDF出力時の横幅設定は0.1単位と小さくなってます。（出力を4倍する都合上）
  設定数を4倍するなどで対処できますが、いまのところは生のサイズで使っています。</dd>
    <br>
  <dt>登録人数について</dt>
  <dd>スタッフデータの入力する人数上限を決めているのは、すべてブラウザで入力するのは考えにくいかと思いますので制限してます。
    プログラム自体はデータベースにつなぐ前提で作ったものなので制限なく自由にできます。そのためCSVデータがあれば人数制限なく
    表示させてます。</dd>
    <br>
  <dt>概算給与計算について</dt>
  <dd>休憩考慮チェックを入れると法定休憩を加味した計算になり、残業、深夜勤務も考慮して計算します。
    深夜勤務者が翌6時以降も勤務した場合、勤務開始日に計算されます。ただし、連続勤務でも別々に入力した場合は
    別の日として計算します。
    また、月概算の計算根拠は【週×4.35　→ 年365日÷12÷７】となっています。
    スタッフ別に年間概算を出しているのは、扶養内金額をオーバーしないか？をざっと知るためのものです。</dd>
  <br>
</dl>
</span>
</div>

<div id="dialog2">
<p　class="subheading">【仕様の解説】</p>
<button id="close2">閉じる</button>
<button id="open3">表示について</button>

<span class="kaisetu_left">
<dl>
  <dt>作成言語</dt>
  <dd>PHPで集計、HTML、CSSで表示し、表示コントロールにJavascriputを少し使っています。
    PDF出力はライブラリのTCPDFを利用しています。
    これはMySQLのデータベースとつないでいませんが、データベースとつなげて使う想定で作成されたものです。<br>
  </dd>
  <br>
  <dt>発展させた使い道</dt>
  <dd>このサイトはブラウザからデータを入力、もしくはCSVデータを読み込んで表示してますが、
    もともとはMySQL上にスタッフデータベースを作成して、契約書発行や更新、連絡などの業務管理をしながら、
    基本シフトを確認できるものとして作成したものです。複数店舗管理をする上で、各店のシフト状況、アルバイトの契約更新状況がタイムリーで把握できるものは重宝します。<br>
    例えば、シフトの人時（１時間あたりの人数）を２名と管理者が設定しておけば、店長がデータを更新したときに、1名以下しかいないのであれば不足、
    3名以上になっていれば余剰と判断材料になります。必ずしもその数字で画一判断できませんが、現地に行かなくてもある程度管理できるようになれば、
    業務軽減は大きなものになります。そんな複数店舗管理者向けのシステムのサンプルは
    <a class="kaisetu" href="../s_manage/index.php" target="_blank" rel="noopener noreferrer" >サンプル集</a>
    にございます。
  </dd>
</dl>
</span>
</div>


</div>
<br><br>

<footer>
  <p>
    <a href="index.php">シフト表ホーム</a>
    &emsp;
    <a href="../s_manage/mail_form/mailform.php">お問い合わせ</a>
    &emsp;
    <a href="../s_manage/index.php">サンプル集（プロフィール）</a>
  </p>
  <p>製作者：Katsuyuki Mitsuoka</p>
</footer>

</body>
</html>

<script type="text/javascript">
<!--
function cellsize(){

  var paper = document.getElementById('paper');
  var paper_s = document.getElementById('paper_s');
  var cell_h = document.getElementById('cell_h');
  var cell_w = document.getElementById('cell_w');
  var cell_f = document.getElementById('cell_f');

  var p = paper.value;
  var ps = paper_s.value;

  cell_h.value = "0";
  cell_w.value = "0";
  cell_f.value = "0";

  if(ps=="A4"){
    cell_f.value = "6";
  }else{
    cell_f.value = "12";
  }

  if(p=="L" && ps=="A4"){
    cell_h.value = "5";
    cell_w.value = "2.1";
  }
  if(p=="P" && ps=="A4"){
    cell_h.value = "8";
    cell_w.value = "1.6";
  }
  if(p=="L" && ps=="A3"){
    cell_h.value = "7";
    cell_w.value = "3";
  }
  if(p=="P" && ps=="A3"){
    cell_h.value = "11";
    cell_w.value = "2";
  }


}

var setumei0 = document.getElementById('setumei0');
var open0 = document.getElementById('open0');

var dialog1 = document.getElementById('dialog1');
var open1 = document.getElementById('open1');
var close1 = document.getElementById('close1');
var dialog2 = document.getElementById('dialog2');
var open2 = document.getElementById('open2');
var close2 = document.getElementById('close2');
var open3 = document.getElementById('open3');
var open4 = document.getElementById('open4');
var close3 = document.getElementById('close3');

open0.addEventListener('click', function() {
  setumei0.classList.toggle('show');
  setumei0.classList.toggle('hide');
});

open4.addEventListener('click', function() {
  dialog3.style.display = 'block';
});
close3.addEventListener('click', function() {
  dialog3.style.display = 'none';
});


open1.addEventListener('click', function() {
  dialog1.style.display = 'block';
});
open2.addEventListener('click', function() {
  dialog1.style.display = 'none';
  dialog2.style.display = 'block';
});
open3.addEventListener('click', function() {
  dialog2.style.display = 'none';
  dialog1.style.display = 'block';
});



close1.addEventListener('click', function() {
  dialog1.style.display = 'none';
});
close2.addEventListener('click', function() {
  dialog2.style.display = 'none';
});

-->
</script>
