<?php
//クリックジャッキング対策
header('X-FRAME-OPTIONS: SAMEORIGIN');

//XSS対策（h関数を読み込む）
require_once 'util.php';

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

//POSTで入力データを得る

//出勤、退勤データ
for($i=0;$i<7;$i++){//1週間分ループ
  if(isset($_POST["in_$i"])){
    ${'in_'.$i} =$_POST["in_$i"];
  }else{
    ${'in_'.$i} = array();
  }
  if(isset($_POST["out_$i"])){
    ${'out_'.$i} =$_POST["out_$i"];
  }else{
    ${'out_'.$i} = array();
  }
}

//シフトデータ配列準備
$staff = $shiftcolor = $per_hour = array();

//スタッフ名
if(isset($_POST['staff'])){
  $staff =$_POST['staff'];
}

//シフト色
if(isset($_POST['shiftcolor'])){
  $shiftcolor =$_POST['shiftcolor'];
}

//時給
if(isset($_POST['per_hour'])){
  $per_hour =$_POST['per_hour'];
}

//休憩
$rest = "";
if(isset($_POST['rest'])){
  $rest = $_POST['rest'];
}

$browser = "Chrome";
$ta_css = 'ta_time';//表示基準はchromeにする

if(isset($_POST['ta_css'])){//入力表示のスタイル変更
  if($_POST['ta_css'] == "Safari"){
    $browser = "Safari";
    $ta_css = ' class="ta_time_safari"';
  }
}

//スタッフ人数変更
$m_staff=30;//最大数はここで変更
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

//デモ用シフトデータの準備
if ($sp == 1){
  //格納変数初期化
  for($i=0;$i<7;$i++){
      ${'in_'.$i} = array();
      ${'out_'.$i} = array();
  }
  //シフトデータ配列準備
  $staff = $shiftcolor = $per_hour = array();

  //CSVフィアルを読み込み
  $filename = "sample/shift.csv";
  $filedata = file_get_contents($filename);
  //一時ファイルを作って保存
  $fp = tmpfile();
  fwrite($fp, $filedata);
  // ファイルポインタを先頭にする
  rewind($fp);
  // ローケルを設定
  setlocale(LC_ALL, 'ja_JP.UTF-8');

  while ($arr = fgetcsv($fp)) {
    if (! array_diff($arr, array(''))) { //空行を除外
      continue;
    }
    //CVSカラムの項目　業務は整形してすべて読み込む
    //店番	名前	時給	色	月	月	火	火	水	水	木	木	金	金	土	土	日	日
    list( $sh_sno,$sh_na,$sh_phour,$sh_color,$sh_in0,$sh_out0,$sh_in1,$sh_out1,$sh_in2,$sh_out2,
    $sh_in3,$sh_out3,$sh_in4,$sh_out4,$sh_in5,$sh_out5,$sh_in6,$sh_out6,) = $arr;

    if($sh_sno != "店番"){//項目（カラム）省く
      $staff[]      = h($sh_na);
      $per_hour[]   = h($sh_phour);
      $shiftcolor[] = h($sh_color);

      for($i=0;$i<7;$i++){
        ${'in_'.$i}[]  = h(${'sh_in'.$i});
        ${'out_'.$i}[] = h(${'sh_out'.$i});
      }
    }
  }
  //ファイルロックを解除
  fflush($fp);
  flock($fp, LOCK_UN);
  //ファイルを閉じる
  fclose($fp);
}//===================シフト読み込みデータここまで

//最大入力人数が20人以上のときは空白を入れておく
if($max_staff > count($staff)){
  $m=$max_staff-count($staff);
  for($i=0;$i<$m;$i++){
    $staff[]      = "";
    $per_hour[]   = "";
    $shiftcolor[] = "";
    for($j=0;$j<7;$j++){
      ${'in_'.$j}[]  = "";
      ${'out_'.$j}[] = "";
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
<title>シフト表イメージ</title>
<link rel="stylesheet" type="text/css"
href="css/shiftstyle.css?<?php echo date('Ymd-Hi');?>">
</head>

<body>

<header class="menuheder">
  <h1>シフト表作成</h1>
  <p>（週間固定シフト表示サンプル）</p>
</header>

<div class="content">

<p class="Explanation">
  朝6時〜翌朝6時表記のシフト表です。<br>
  下のフォームに情報入力して、表示ボタンを押してください。ボタンの下にサンプルが表示されます。<br>
  <br>
  Sampleボタンを押すとサンプル情報が自動入力されます。サンプル情報は20人分までしか入ってません。
  自動入力された情報は追加変更できます。ただし、人数変更すると入力が消えますのでご注意ください。<br>
  また、PDF出力できるようになってますが、A4横でしか出せず、シフトが多いと表が潰れて出力されます。
  出力させる際に紙のサイズや縦横、行の高さ、幅なども調整できると使いやすいですね。また作成します。
</p><br>

<div class="pc_window">
  <p class="ta_css_p">ブラウザ切替え用（標準：Chrome)</p>
  <form method="POST" action="<?php echo h($_SERVER['SCRIPT_NAME']);?>">
    <button type="submit" name="ta_css" class="smplebutton" value="Chrome">Chrome</button>
    <button type="submit" name="ta_css" class="smplebutton" value="Safari">Safari</button>
  </form>
<br>
</div>

<p>
  <form method="POST" action="<?php echo h($_SERVER['SCRIPT_NAME']);?>">
    <span class="small_text">スタッフ基本データ入力(</span>
    <input type="number" min="10" max="30" step="1" value="<?php echo $max_staff; ?>" name="max_staff">
    <span class="small_text">人)</span>
    <button type="submit" class="smplebutton">人数変更</button>
    <input type="hidden" name="ta_css" value="<?php echo $browser; ?>" >
    <button type="submit" value="1" class="smplebutton" name="sample">Sample</button>
  </form>
</p>

<div class="pc_window">
<p>
  <form method="POST" action="<?php echo h($_SERVER['SCRIPT_NAME']);?>#result1">
    <input type="hidden" name="max_staff" value="<?php echo $max_staff; ?>">
    <input type="hidden" name="ta_css" value="<?php echo $browser; ?>" >
  <span class="Annotation">
    ↑↑注意：人数はサンプルなので10〜30人に制限しています。<br></span>
    概算給与は休憩考慮⇒<input type="checkbox" value="1" name='rest'></p>

<table>
  <thead class="add">
  <tr>
    <th rowspan="2" class="ta_name">表示名</th>
    <th rowspan="2" class="ta_perhour">時給</th>
    <th rowspan="2" class="ta_shift">シフト色</th>
    <?php
    for($i=0;$i<7;$i++){
      echo '<th colspan="2">',$week[$i],'</th>';
    }
    ?>
  </tr>
  <tr>
    <?php for($i=0;$i<7;$i++){
    echo '<th class="',$ta_css,'">開始</th><th class="',$ta_css,'">終了</th>';
    }?>
  </tr>
</thead>

<tbody class="add">
  <?php
  for($i=0;$i<$max_staff;$i++){  ?>
  <tr>
    <td class="ta_name"><input type='text' name='staff[]' class="shift" maxlength="6" value="<?php echo $staff[$i] ;?>"></td>
    <td class="ta_perhour"><input type='numbar' name='per_hour[]' class="per_hour" maxlength="6" value="<?php echo $per_hour[$i] ;?>"></td>
    <td>
      <select name='shiftcolor[]' class="ta_time_val" >
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
            echo '<option value="',$j,'"',$re,'>', $j,"</option>" ;
         }
         ?>
      </select>
    </td>
    <td>
      <select name=<?php echo "out_".$w."[]";?>  class="ta_time_val">
        <option></option>
        <?php
         for ($j=0;$j<24;$j++){
           $re="";
           if($j == ${'out_'.$w}[$i] && ${'out_'.$w}[$i]!=""){
             $re = " selected";
           }
           echo '<option value="',$j,'"',$re,'>', $j,"</option>";
         }
         ?>
      </select>
    </td>
<?php }
  echo '</tr>';
 }
?>
</tbody>
  <tr>
    <td class="sukima" colspan="17">
      <button type="submit" value="1" class="button" name="hyo">表示</button>
    </td>
  </tr>
</form>
</table>
</div><!-- PC画面ここまで-->

<div class="phone_window">
<p>
  <form method="POST" action="<?php echo h($_SERVER['SCRIPT_NAME']);?>#result1">
    <input type="hidden" name="max_staff" value="<?php echo $max_staff; ?>">
    <input type="hidden" name="ta_css" value="<?php echo $browser; ?>" >
  <span class="Annotation">
    ↑↑注意：人数はサンプルなので10〜30人に制限しています。<br></span>
    概算給与は休憩考慮<input type="checkbox" value="1" name='rest'></p>

<table class="p_spread">
  <tr>
    <th rowspan="2" class="left_header">表示名</th>
    <th rowspan="2">時給</th>
    <th rowspan="2">シフト色</th>
    <?php
    for($i=0;$i<7;$i++){
      echo '<th colspan="2">',$week[$i],'</th>';
    }
    ?>
  </tr>
  <tr>
    <?php for($i=0;$i<7;$i++){
    echo "<th>開始</th><th>終了</th>";
    }?>
  </tr>
  <?php
  for($i=0;$i<$max_staff;$i++){  ?>
  <tr>
    <td class="left_header"><input type='text' name='staff[]' class="shift" maxlength="6" value="<?php echo $staff[$i] ;?>"></td>
    <td><input type='numbar' name='per_hour[]' class="per_hour" maxlength="6" value="<?php echo $per_hour[$i] ;?>"></td>
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
<?php } ?>
  </tr>
<?php } ?>
</table>
  <button type="submit" value="1" class="button" name="hyo">表示</button>
  </form>


</div><!--スマホ画面ここまで-->


<br>
<?php
if($hyo==1){
//入力されたデータをSQLから読み込むように表形式の連想配列で曜日ごとに読み込む
$data = $yesterday = array();//配列変数初期化

//月曜シフト作成用に日曜夜勤の繰越を先に配列に入れ込む 変数名数字は６
$yc=0;//繰越配列用のカウント
if(count($in_6)!= 0){
  for($i=0;$i<count($in_6);$i++){
    if($in_6[$i] > $out_6[$i] && $out_6[$i] > 6){
      $yesterday[$yc] = array('name' => $staff[$i],'in' => 6,'out' => $out_6[$i],'shiftcolor' => $shiftcolor[$i],);
      $yc++;
    }
  }
}

for($w=0;$w<7;$w++){
if(count(${'in_'.$w})!=0){
  for($c=0;$c<count(${'in_'.$w});$c++){//開始時間が0時から5時までの人は先に２４を加算しておく
    if($staff[$c]!="" && ${'in_'.$w}[$c]!="" && ${'out_'.$w}[$c]!="" && ${'in_'.$w}[$c] != ${'out_'.$w}[$c]){
    if(${'in_'.$w}[$c] < 6){ ${'in_'.$w}[$c] = ${'in_'.$w}[$c] + 24 ;}
    }
  }
  for($i=0;$i<count(${'in_'.$w});$i++){
    if($staff[$i]!="" && ${'in_'.$w}[$i]!="" && ${'out_'.$w}[$i]!="" && ${'in_'.$w}[$i] != ${'out_'.$w}[$i]){
      if($shiftcolor[$i]==""){$c_color="shift";}else{$c_color=$shiftcolor[$i];}
      if(${'out_'.$w}[$i] < ${'in_'.$w}[$i]){
        $data[$i] = array('name' => $staff[$i],'in' => ${'in_'.$w}[$i],'out' => ${'out_'.$w}[$i]+24,'shiftcolor' => $c_color,);
      }else{
        $data[$i] = array('name' => $staff[$i],'in' => ${'in_'.$w}[$i],'out' => ${'out_'.$w}[$i],'shiftcolor' => $c_color,);
      }
    }
  }

  if(count($yesterday)!=0){
    foreach($yesterday as $yd){
      $i++;
      $data[$i] = array('name' => $yd['name'],'in'=> $yd['in'],'out'=> $yd['out'],'shiftcolor' => $yd['shiftcolor'],);
    }
    $yesterday = array();//前日の夜勤が伸びた場合用
  }
}

if(count($data)!=0){//====================シフト表の表示
?>
    <!--- テーブルのタイトル行--->
<table class="white" id="result1">
      <tr>
        <th class="shift1">時間</th>
        <?php
        for ($i=6;$i<=29;$i++){
          if($i>=24){
        ?>
            <th class="shift2"><?php echo $i-24;?></th>
        <?php
          }else{
        ?>
            <th class="shift2"><?php echo $i;?></th>
        <?php
          }
        }
        ?>
      </tr>
      <tr>
        <th rowspan="0" class="shift1"><?php echo $week[$w]."曜日";?></th>
        <?php
//曜日ごとのデータをシフト1行ずつに成形しながら表示する
do{

        $FL=0;//初回かどうかのチェック用
        $cnt=0;//表示用連想配列用　格納用添字
        $removed=array();

        foreach($data as $ss){
        $min=99;//最小の時間を探す　inの時間が入る
        $m_out="";//条件にあったout時間
        $c_cnt=0;//データカウント用　該当データ

        if ($FL == 0){
          $hantei=6;//ここはループのたびに変える
        }
          foreach($data as $m_shift){
            if ($min >= $m_shift['in'] && $m_shift['in'] >= $hantei){
                $min=$m_shift['in'];
                $m_out=$m_shift['out'];
                $cnt=$c_cnt;
             }
             $c_cnt++;
           }

        if ($min == 99){ break; }
          //配列からデータを消しながら表示データを作る（二重表示を防ぐ）
           $removed = array_merge_recursive($removed,array_splice($data,$cnt,1));
           $hantei = $m_out;
           $FL=1;
           $cnt++;
        }

$c_in=99;
$c_out=0;
$cck=0;
$i=6;

do{
  $c=0;//表示用配列の削除用
  $y_count=0;//翌日に繰り越す用のカウント
  foreach($removed as $cc){
   if($cc['in'] == $i){
     if($cc['out']>30){//退勤が6時以上のシフトはカットする
       $cco=30;
       //繰越用のシフトに入れる
       $yesterday[$y_count] = array('name' => $cc['name'],'in' => 6,'out' => $cc['out']-24,'shiftcolor' => $cc['shiftcolor'],);
       $y_count++;
     }else{
       $cco=$cc['out'];
     }
     $h_col= 'colspan="'.($cco - $cc['in']).'"';
     echo '<td ',$h_col,' class="',$cc['shiftcolor'],'"','>',$cc['name'],"</td>";
     $c_in = $cc['in'];
     $c_out = $cc['out'];
     $i=$cc['out']-1;
     $dustbox = array_splice($removed,$c,1);
     break;
   }
  }
 if ($i < $c_in or $i >= $c_out){
    echo '<td class="','sukima"','></td>';
 }
 $i = $i + 1;
}while($i <= 29);

echo "</tr>";
//データがなくなったか配列のカウントをさせて判断
$ck_cnt = count($data);

}while($ck_cnt != 0);

}
}//7回繰り返し

echo '</table>';//シフト表作成ここまで

//配列をカンマ区切りにしてPOSTしてPDF作成する
if(count($staff)!=0){
  //最初のデータはそのまま入れる
  $staff_d = $staff[0];
  $shiftcolor_d = $shiftcolor[0];
  for($i=0;$i<7;$i++){
    ${'in_d_'.$i} =${'in_'.$i}[0];
    ${'out_d_'.$i} =${'out_'.$i}[0];
  }
  //2番めのデータからカンマ区切で追加
  for($j=1;$j<count($staff);$j++){
    $staff_d .= ",".$staff[$j];
    $shiftcolor_d .= ",".$shiftcolor[$j];
    for($i=0;$i<7;$i++){
      ${'in_d_'.$i} .= ",".${'in_'.$i}[$j];
      ${'out_d_'.$i} .= ",".${'out_'.$i}[$j];
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
  <button type="submit" name="s_data" class="button" value="">PDF出力</button>
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
      $in_h = sprintf("%02d",${'in_'.$w}[$i]).":"."00";
      $out_h = sprintf("%02d",${'out_'.$w}[$i]).":"."00";
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
?>

<div id="dialog1">
<p class="subheading">【シフト表の解説】</p>
<button id="close1">閉じる</button>
<button id="open2">仕様について</button>

<span class="kaisetu_left">
<dl>
  <dt>表示の工夫</dt>
  <dd>データがどの順番でも、表の隙間を上から順に埋めて表示し、同じ時間で勤務が重なっても行数は自動で増え、
    深夜勤務者が翌日6時以降で勤務した場合、翌日に表示されます。日曜日の深夜勤務者の場合、月曜に反映されます。
    ただし、24時間以上のシフトは対応していません。必要なら実装方法はあります。</dd>
    <br>
  <dt>時間単位について</dt>
  <dd>サンプルでわかりやすく1時間単位ですが、任意で単位を刻むのは簡単に修正可能です。シフトなので最小単位は15分程度でよいと思いますが、
    1分でも理論上は可能です。（表示に工夫は必要ですが。。。）</dd>
    <br>
  <dt>登録人数について</dt>
  <dd>サンプルデータを準備するのが大変なので入力制限をかけていますが、プログラム自体はデータベースにつなぐ前提で作ってますので、制限なく自由にできます。
    「店舗管理システム」では、人数制限なく表示させてます。</dd>
    <br>
  <dt>概算給与計算について</dt>
  <dd>休憩考慮チェックを入れると法定休憩を加味した計算になり、深夜勤務者が翌日勤務した場合、勤務開始日に計算されます。
    ただし、サンプルでは、連続勤務でも別々に入力した場合は別の日として計算します。IDを各自割り当てれば合算も可能です。
    基本シフトの段階で残業、週残業を加味したものは考えづらいですが、各残業計算はできるようになっています。
    また、店舗管理者は基本シフトを組んだ時、月概算（週×4.35　→ 年365日÷12÷７)を知りたいので表示し、さらにアルバイトは
    自分が月間どれぐらい稼げるか？年間で扶養内金額をオーバーしないか？を知りたいので、表示しています。</dd>
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
  <dd>PHPで集計、HTML、CSSで表示し、サンプルデータはJavascriptで呼び出しています。
    MySQLのデータベースとつないでいませんが、本運用ではデータベースとつなげて使います。<br>
    概算給与計算は、<a class="kaisetu" href="payroll.php" target="_blank" rel="noopener noreferrer" >勤務時間（法定休憩時間）の計算</a>サンプルを使っています。</br>
    時間帯手当など、時間によって加給されるものは想定していませんが、深夜手当判断と同様の手段で別に考えれば追加は可能です。</br>
  </dd>
  <br>
  <dt>使い道</dt>
  <dd>このサンプルはブラウザからデータを入力して表示してますが、実際はMySQL上にアルバイトさんのデータを入れ、
    契約書を発行や更新などの管理をしながら、基本シフトも見れるものとして運用を想定しています。<br>
    また、複数店舗管理をする上では、各店のシフト状況、アルバイトの契約更新状況がタイムリーで把握できるものは重宝します。<br>
    例えば、シフトの人時（１時間あたりの人数）を２名と管理者が設定しておけば、店長がデータを更新したときに、1名以下しかいないのであれば不足、
    3名以上になっていれば余剰と判断材料になります。必ずしも画一できませんが、現地に行かなくても管理できるようになれば、業務軽減は大きなものになります。
    そういった人時も計算に組み込み、確認やアラートが出ると対応が早くなりますね。
  </dd>
</dl>
</span>
</div>

</div>
<br><br>

<footer></footer>

</body>
</html>

<script type="text/javascript">
<!--

var dialog1 = document.getElementById('dialog1');
var open1 = document.getElementById('open1');
var close1 = document.getElementById('close1');
var dialog2 = document.getElementById('dialog2');
var open2 = document.getElementById('open2');
var close2 = document.getElementById('close2');
var open3 = document.getElementById('open3');

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
