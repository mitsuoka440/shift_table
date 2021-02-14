<?php
//クリックジャッキング対策
header('X-FRAME-OPTIONS: SAMEORIGIN');

//POSTで入力データを得る
for($i=0;$i<7;$i++){
  if(isset($_POST['in'.$i])){//出勤7日分をループ
    ${'in_'.$i} =explode(",",$_POST['in'.$i]);
  }else{
    ${'in_'.$i} = array();//POST前は空の配列にしておく
  }
  if(isset($_POST['out'.$i])){//出勤7日分をループ
    ${'out_'.$i} =explode(",",$_POST['out'.$i]);
  }else{
    ${'out_'.$i} = array();//POST前は空の配列にしておく
  }
}

if(isset($_POST['staff'])){//スタッフ名
  $staff =explode(",",$_POST['staff']);
}else{
  $staff = array();
}

if(isset($_POST['shiftcolor'])){//シフト色
  $shiftcolor =explode(",",$_POST['shiftcolor']);
}else{
  $shiftcolor = array();
}

//出力用の曜日を配列格納　datetimeに紐付けしてないので注意
$week = ['月','火','水','木','金','土','日'];

//色データ判別用
$color_index = ["red1","blue1","purple1","green1","yellow1","red2","blue2","purple2","green2","yellow2"];
//RGB　R
$color_r = [255,0,128,0,255,255,30,186,50,255];
//RGB　G
$color_g = [0,0,0,128,255,20,144,85,205,165];
//RGB　B
$color_b = [0,255,128,0,0,147,255,211,50,0];
//シフトのセル高さ
$shift_h=5;
//シフトのセル幅
$shift_w=9;


// TCPDFをインクルード
require_once (dirname(__FILE__).'/tcpdf/tcpdf.php');
require_once (dirname(__FILE__).'/tcpdf/fpdi/autoload.php');

// TCPDFオブジェクトをnew演算子で作成します。
$pdf = new setasign\Fpdi\Tcpdf\Fpdi();

// ヘッダー／フッターを削除します。
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

//---ここから関数にするなど工夫をすると違うPDFベースで一緒のファイルで作成可能
//関数にするときは代入する文字列はもちろん、＄PDFのObjectも関数へ渡すとうまくいく


//文書のプロパティを設定します。
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Katsuyuki_Mitsuoka');
$pdf->SetTitle('基本シフト表');
$pdf->SetSubject('1週間');
$pdf->SetKeywords('shift');


//自動改ページを有効にします。(True or False,下マージン)
//ページのギリギリに表示するときはFalseにしないと自動で改ページされる
$pdf->SetAutoPageBreak(False, PDF_MARGIN_BOTTOM);

// ページを追加します。（用紙の向き P：縦 or L:横 ,サイズ　用紙サイズ　カスタマイズ可）
$pdf->AddPage('L',"A4");
//ページのマージン設定。（左、上、右)
$pdf->SetMargins(0, 0, 0);

//スタイルは、’’regyular,Bがbold(太字）、Iイタリック、Uアンダーライン、D取り消し
//MS ゴシック（msgothic)、MS Pゴシック(mspgothic)、MS P明朝(mspmin)は設定済み
//$pdf->Text(x座標, y座標, テキスト);

//表の表示
$pdf->SetFont('mspmin','',20);//フォント指定　MSP明朝
$pdf->Text(117,10,'基本シフト');
$pdf -> Ln(10);//改行数字はmargin

//MultiCell( $w, $h, $txt, $border, $align, $fill, $ln, $x, $y,
//      $reseth, $stretch, $ishtml, $autopadding, $maxh, $valign, $fitcell )

//POSTデータの整理　連想配列で曜日ごとに読み込む
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
  for($i=0;$i<count(${'in_'.$w});$i++){//バラバラのデータを連想配列に加工（当該曜日のみ）
    if($staff[$i]!="" && ${'in_'.$w}[$i]!="" && ${'out_'.$w}[$i]!="" && ${'in_'.$w}[$i] != ${'out_'.$w}[$i]){
      if($shiftcolor[$i]==""){$c_color="shift";}else{$c_color=$shiftcolor[$i];}
      if(${'out_'.$w}[$i] < ${'in_'.$w}[$i]){
        $data[$i] = array('name' => $staff[$i],'in' => ${'in_'.$w}[$i],'out' => ${'out_'.$w}[$i]+24,'shiftcolor' => $c_color,);
      }else{
        $data[$i] = array('name' => $staff[$i],'in' => ${'in_'.$w}[$i],'out' => ${'out_'.$w}[$i],'shiftcolor' => $c_color,);
      }
    }
  }

  if(count($yesterday)!=0){//夜勤の繰越者がいれば、連想配列に付け加える
    foreach($yesterday as $yd){
      $i++;
      $data[$i] = array('name' => $yd['name'],'in'=> $yd['in'],'out'=> $yd['out'],'shiftcolor' => $yd['shiftcolor'],);
    }
    $yesterday = array();//前日の夜勤が伸びた場合用
  }
}

$first=0;

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

  if($first==0){
    $we=$week[$w].'曜日';
    $line='L1 T1 R1';
    //表のタイトル
    $pdf->SetFillColor(102,102,102,true);//背景色　濃いグレー
    $pdf->SetTextColor(0,0,0);//フォント黒
    $pdf->SetFont('mspgothic','',10);//フォント指定　MSPゴシック
    $pdf -> MultiCell($shift_w*2,$shift_h,'時間',1,'C',true,0,16,'',true,0,false,true,$shift_h,'M',false);

    for ($h=6;$h<=29;$h++){
        if($h>=24){
          if($h==29){
            $pdf -> MultiCell($shift_w,$shift_h,$h-24,1,'C',true,1,'','',true,0,false,true,$shift_h,'M',false);
          }else{
            $pdf -> MultiCell($shift_w,$shift_h,$h-24,1,'C',true,0,'','',true,0,false,true,$shift_h,'M',false);
          }
        }else{
          $pdf -> MultiCell($shift_w,$shift_h,$h,1,'C',true,0,'','',true,0,false,true,$shift_h,'M',false);
        }
      }
      $pdf->SetFont('mspgothic','',6);//フォント指定　MSPゴシック
      $pdf->SetFillColor(102,102,102,true);//背景色　濃いグレー
    $first=1;
  }else{
    $pdf->SetFillColor(255,255,255,true);//背景色　白
    $we="";
    $line='L1 R1';
  }

  $pdf -> MultiCell($shift_w*2,$shift_h,$we,$line,'C',true,0,16,'',true,0,false,true,$shift_h,'M',false);

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
     $h_col= ($cco - $cc['in'])*$shift_w;//セルの幅の計算
     $s_cell = (16+$shift_w*2)+($cc['in']-6)*$shift_w;//セルの始まりの場所
     $c_i=0;//色の決定
     foreach($color_index as $ci){
       if($ci==$cc['shiftcolor']){
         break;
       }
       $c_i++;
     }
     $re = 0;//改行するかどうか
     if($cco==30){
       $re=1;
     }
     $pdf->SetFillColor($color_r[$c_i],$color_g[$c_i],$color_b[$c_i],true);//背景色　シフト塗り潰し
     $pdf -> MultiCell($h_col,$shift_h,$cc['name'],1,'C',true,$re,$s_cell,'',true,0,false,true,$shift_h,'M',false);

     $c_in = $cc['in'];
     $c_out = $cc['out'];
     $i=$cc['out']-1;
     $dustbox = array_splice($removed,$c,1);
     break;
   }
 }
 if ($i < $c_in or $i >= $c_out){
    $re = $line = 0;
    $s_cell = (16+$shift_w*2)+($i-6)*$shift_w;//セルの始まりの場所
    if($i>=29){
       $re=1;
       $line='R1';
     }
     $pdf -> MultiCell($shift_w,$shift_h,'',$line,'C',false,$re,$s_cell,'',true,0,false,true,$shift_h,'M',false);
   }
  $i = $i + 1;
}while($i <= 29);

//データがなくなったか配列のカウントをさせて判断
$ck_cnt = count($data);

}while($ck_cnt != 0);

}//7回繰り返し

$pdf -> MultiCell(($shift_w*26),2,'','T1','C',false,1,16,'',true,0,false,true,2,'M',false);


# Internet ExplorerがContent-Typeヘッダーを無視しないようにする
header('X-Content-Type-Options: nosniff');
# PDFを動的に出力します。
$pdf->Output('shift_chart.pdf','I');
