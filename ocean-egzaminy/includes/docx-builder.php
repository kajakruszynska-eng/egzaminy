<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Minimalistyczny generator DOCX w czystym PHP.
 * DOCX = ZIP zawierający pliki XML (Open XML format).
 * Nie wymaga żadnych zewnętrznych bibliotek.
 * Wymaga rozszerzenia PHP: ZipArchive (dostępne na home.pl).
 */
class OE_Docx {

    // A4 w EMU (English Metric Units): 1 cm = 360000 EMU, 1 twip (DXA) = 635 EMU
    // A4: 210mm × 297mm = 11906 × 16838 DXA
    const A4_W  = 11906;
    const A4_H  = 16838;
    const A4_LS_W = 16838; // landscape
    const A4_LS_H = 11906;
    const MARGIN  = 720;   // ~1.27 cm

    private $paragraphs = [];
    private $mt=720,$mr=720,$mb=720,$ml=720; private $landscape=false;
    private $numeracja = true; // footer "str. X z Y"

    public function __construct() {}

    /** Turn the "str. X z Y" page number footer on or off (on by default). */
    public function setNumeracja( $on ) { $this->numeracja = (bool) $on; }

    public function setMargins($top,$right,$bottom,$left) {
        $this->mt=$top; $this->mr=$right; $this->mb=$bottom; $this->ml=$left;
    }

    public function setLandscape($ls) { $this->landscape = $ls; }

    // ── Public API ────────────────────────────────────────────────────────

    /** Dodaj akapit */
    public function p( $tekst = '', $opts = [] ) {
        $this->paragraphs[] = $this->buildParagraph( $tekst, $opts );
        return $this;
    }

    /** Akapit z wieloma runami (różne formatowanie w jednej linii) */
    public function pRuns( array $runs, $opts = [] ) {
        $this->paragraphs[] = $this->buildParagraphRuns( $runs, $opts );
        return $this;
    }

    /** Pusta linia */
    public function br( $n = 1 ) {
        for ( $i = 0; $i < $n; $i++ ) {
            $this->paragraphs[] = $this->buildParagraph( '' );
        }
        return $this;
    }

    /** Dodaj tabelę */
    public function table( array $rows, array $colWidths, $opts = [] ) {
        $this->paragraphs[] = $this->buildTable( $rows, $colWidths, $opts );
        return $this;
    }

    /** Podział strony */
    public function pageBreak() {
        $this->paragraphs[] = '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
        return $this;
    }

    /** Zapisz do pliku i zwróć ścieżkę, lub wyślij jako download */
    public function save( $filepath ) {
        $xml = $this->buildDocument();
        return $this->createZip( $filepath, $xml );
    }

    public function download( $filename ) {
        $tmp = tempnam( sys_get_temp_dir(), 'oe_docx_' );
        $this->save( $tmp );
        header( 'Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . filesize( $tmp ) );
        header( 'Pragma: no-cache' );
        readfile( $tmp );
        @unlink( $tmp );
        exit;
    }

    // ── Budowniczy XML ────────────────────────────────────────────────────

    private function buildParagraph( $tekst, $opts = [] ) {
        $align  = $opts['align'] ?? 'left';
        $bold   = ! empty( $opts['bold'] );
        $italic = ! empty( $opts['italic'] );
        $size   = $opts['size'] ?? 20; // half-points: 20 = 10pt, 24 = 12pt
        $before = isset($opts['before']) ? intval($opts['before']) : 0;
        $after  = isset($opts['after'])  ? intval($opts['after'])  : 80;

        $pPr = $this->pPr( $align, $before, $after );
        $rPr = $this->rPr( $bold, $italic, $size, ! empty( $opts['underline'] ) );
        $t   = $this->escXml( $tekst );

        return "<w:p>{$pPr}<w:r>{$rPr}<w:t xml:space=\"preserve\">{$t}</w:t></w:r></w:p>";
    }

    private function buildParagraphRuns( array $runs, $opts = [] ) {
        $align  = $opts['align'] ?? 'left';
        $before = isset($opts['before']) ? intval($opts['before']) : 0;
        $after  = isset($opts['after'])  ? intval($opts['after'])  : 80;
        $pPr    = $this->pPr( $align, $before, $after );

        $runsXml = '';
        foreach ( $runs as $r ) {
            $bold   = ! empty( $r['bold'] );
            $italic = ! empty( $r['italic'] );
            $size   = $r['size'] ?? 20;
            $rPr    = $this->rPr( $bold, $italic, $size, ! empty( $r['underline'] ) );
            $t      = $this->escXml( $r['text'] ?? '' );
            $runsXml .= "<w:r>{$rPr}<w:t xml:space=\"preserve\">{$t}</w:t></w:r>";
        }

        return "<w:p>{$pPr}{$runsXml}</w:p>";
    }

    private function pPr( $align, $before, $after ) {
        switch( $align ) {
            case 'center': $jc = '<w:jc w:val="center"/>'; break;
            case 'right':  $jc = '<w:jc w:val="right"/>'; break;
            default:       $jc = ''; break;
        }
        return "<w:pPr><w:spacing w:before=\"{$before}\" w:after=\"{$after}\"/>{$jc}<w:rPr><w:rFonts w:ascii=\"Arial\" w:hAnsi=\"Arial\"/></w:rPr></w:pPr>";
    }

    private function rPr( $bold, $italic, $size, $underline = false ) {
        $b = $bold   ? '<w:b/><w:bCs/>' : '<w:b w:val="false"/><w:bCs w:val="false"/>';
        $i = $italic ? '<w:i/><w:iCs/>' : '<w:i w:val="false"/><w:iCs w:val="false"/>';
        $u = $underline ? 'single' : 'none';
        return "<w:rPr><w:rFonts w:ascii=\"Arial\" w:hAnsi=\"Arial\"/>{$b}{$i}<w:u w:val=\"{$u}\"/><w:sz w:val=\"{$size}\"/><w:szCs w:val=\"{$size}\"/></w:rPr>";
    }

    private function buildTable( array $rows, array $colWidths, $opts = [] ) {
        $totalW  = array_sum( $colWidths );
        $borders = $opts['borders'] ?? 'all'; // all | none | outer

        $tblBorders = $this->tblBorders( $borders );

        $tblXml  = "<w:tbl>";
        $tblXml .= "<w:tblPr><w:tblW w:w=\"{$totalW}\" w:type=\"dxa\"/>{$tblBorders}</w:tblPr>";
        $tblXml .= "<w:tblGrid>" . implode('', array_map( function($w) { return "<w:gridCol w:w=\"{$w}\"/>"; }, $colWidths )) . "</w:tblGrid>";

        $rowHeight = isset($opts['rowHeight']) ? intval($opts['rowHeight']) : 0;
        foreach ( $rows as $ri => $row ) {
            $h = $rowHeight;
            // Pierwszy wiersz (nagłówek) może mieć inną wysokość
            if ($ri === 0 && isset($opts['headerHeight'])) $h = intval($opts['headerHeight']);
            $tblXml .= $this->buildRow( $row, $colWidths, $h );
        }

        $tblXml .= "</w:tbl>";
        return $tblXml;
    }

    private function buildRow( array $cells, array $colWidths, $height = 0 ) {
        $trPr = '';
        if ($height > 0) {
            $trPr = "<w:trPr><w:trHeight w:val=\"{$height}\" w:hRule=\"atLeast\"/></w:trPr>";
        }
        $rowXml = "<w:tr>{$trPr}";
        foreach ( $cells as $idx => $cell ) {
            $w    = $cell['w'] ?? ( $colWidths[ $idx ] ?? 1000 );
            $rowXml .= $this->buildCell( $cell, $w );
        }
        $rowXml .= "</w:tr>";
        return $rowXml;
    }

    private function buildCell( $cell, $w ) {
        $vAlign  = $cell['vAlign'] ?? 'center';
        $bg      = $cell['bg'] ?? null;
        $colSpan = $cell['colSpan'] ?? 1;
        $borders = $cell['borders'] ?? 'all';

        $tcPr  = "<w:tcPr>";
        $tcPr .= "<w:tcW w:w=\"{$w}\" w:type=\"dxa\"/>";
        if ( $colSpan > 1 ) {
            $tcPr .= "<w:gridSpan w:val=\"{$colSpan}\"/>";
        }
        if ( $bg ) {
            $tcPr .= "<w:shd w:val=\"clear\" w:color=\"auto\" w:fill=\"{$bg}\"/>";
        }
        $tcPr .= "<w:vAlign w:val=\"{$vAlign}\"/>";
        $tcPr .= "<w:tcMar><w:top w:w=\"0\" w:type=\"dxa\"/><w:left w:w=\"30\" w:type=\"dxa\"/><w:bottom w:w=\"0\" w:type=\"dxa\"/><w:right w:w=\"30\" w:type=\"dxa\"/></w:tcMar>";
        $tcPr .= $this->tcBorders( $borders );
        $tcPr .= "</w:tcPr>";

        // Treść komórki
        $content = '';
        if ( isset( $cell['paragraphs'] ) ) {
            // Gotowe XML akapitów
            foreach ( $cell['paragraphs'] as $pXml ) {
                $content .= $pXml;
            }
        } elseif ( isset( $cell['text'] ) ) {
            $opts = $cell['textOpts'] ?? [];
            $content = $this->buildParagraph( $cell['text'], $opts );
        } else {
            $content = $this->buildParagraph( '' );
        }

        return "<w:tc>{$tcPr}{$content}</w:tc>";
    }

    private function tblBorders( $type ) {
        if ( $type === 'none' ) {
            $b = ' w:val="none" w:sz="0" w:space="0" w:color="auto"';
            return "<w:tblBorders><w:top{$b}/><w:left{$b}/><w:bottom{$b}/><w:right{$b}/><w:insideH{$b}/><w:insideV{$b}/></w:tblBorders>";
        }
        $b = ' w:val="single" w:sz="4" w:space="0" w:color="000000"';
        return "<w:tblBorders><w:top{$b}/><w:left{$b}/><w:bottom{$b}/><w:right{$b}/><w:insideH{$b}/><w:insideV{$b}/></w:tblBorders>";
    }

    private function tcBorders( $type ) {
        if ( $type === 'none' ) {
            $b = ' w:val="none" w:sz="0" w:space="0" w:color="auto"';
        } else {
            $b = ' w:val="single" w:sz="4" w:space="0" w:color="000000"';
        }
        return "<w:tcBorders><w:top{$b}/><w:left{$b}/><w:bottom{$b}/><w:right{$b}/></w:tcBorders>";
    }

    private function buildDocument( $landscape = false ) {
        if ($this->landscape) $landscape = true;
        $body = implode( "\n", $this->paragraphs );
        $pgW = $landscape ? self::A4_LS_W : self::A4_W;
        $pgH = $landscape ? self::A4_LS_H : self::A4_H;
        $orient = $landscape ? ' w:orient="landscape"' : '';
        $mt=$this->mt; $mr=$this->mr; $mb=$this->mb; $ml=$this->ml;
        $stopka     = $this->numeracja ? '
  <w:footerReference w:type="default" r:id="rIdStopka"/>' : '';
        $stopka_odl = $this->numeracja ? 340 : 0; // footer distance from the page edge, inside the bottom margin

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:wpc="http://schemas.microsoft.com/office/word/2010/wordprocessingCanvas"
  xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"
  xmlns:o="urn:schemas-microsoft-com:office:office"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math"
  xmlns:v="urn:schemas-microsoft-com:vml"
  xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:w14="http://schemas.microsoft.com/office/word/2010/wordml"
  mc:Ignorable="w14">
<w:body>
' . $body . '
<w:sectPr>' . $stopka . '
  <w:pgSz w:w="' . $pgW . '" w:h="' . $pgH . '"' . $orient . '/>
  <w:pgMar w:top="' . $mt . '" w:right="' . $mr . '" w:bottom="' . $mb . '" w:left="' . $ml . '" w:header="0" w:footer="' . $stopka_odl . '" w:gutter="0"/>
</w:sectPr>
</w:body>
</w:document>';
    }

    private function createZip( $filepath, $docXml ) {
        if ( ! class_exists('ZipArchive') ) {
            throw new Exception('ZipArchive nie jest dostępne.');
        }

        $zip = new ZipArchive();
        if ( $zip->open( $filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
            throw new Exception('Nie można utworzyć pliku ZIP.');
        }

        $zip->addFromString( '[Content_Types].xml', $this->contentTypes() );
        $zip->addFromString( '_rels/.rels', $this->rels() );
        $zip->addFromString( 'word/document.xml', $docXml );
        $zip->addFromString( 'word/_rels/document.xml.rels', $this->docRels() );
        $zip->addFromString( 'word/settings.xml', $this->settings() );
        if ( $this->numeracja ) {
            $zip->addFromString( 'word/footer1.xml', $this->footer() );
        }
        $zip->close();

        return $filepath;
    }

    private function contentTypes() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>' . ( $this->numeracja ? '
  <Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>' : '' ) . '
</Types>';
    }

    /** Footer "str. X z Y" with PAGE and NUMPAGES fields (Word fills them in). */
    private function footer() {
        $rPr = '<w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial"/><w:sz w:val="16"/><w:szCs w:val="16"/></w:rPr>';
        $pole = function( $instr ) use ( $rPr ) {
            return '<w:fldSimple w:instr=" ' . $instr . ' \* MERGEFORMAT "><w:r>' . $rPr . '<w:t>1</w:t></w:r></w:fldSimple>';
        };
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<w:p><w:pPr><w:jc w:val="right"/><w:spacing w:before="0" w:after="0"/></w:pPr>'
            . '<w:r>' . $rPr . '<w:t xml:space="preserve">str. </w:t></w:r>' . $pole( 'PAGE' )
            . '<w:r>' . $rPr . '<w:t xml:space="preserve"> z </w:t></w:r>' . $pole( 'NUMPAGES' ) . '</w:p>
</w:ftr>';
    }

    private function rels() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>';
    }

    private function docRels() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>' . ( $this->numeracja ? '
  <Relationship Id="rIdStopka" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>' : '' ) . '
</Relationships>';
    }

    private function settings() {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:defaultTabStop w:val="708"/>
  <w:compat><w:compatSetting w:name="compatibilityMode" w:uri="http://schemas.microsoft.com/office/word" w:val="15"/></w:compat>
</w:settings>';
    }

    private function escXml( $str ) {
        return htmlspecialchars( (string)$str, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
    }

    // ── Publiczne builery (do użycia przy budowaniu komórek z zewnątrz) ───
    public function buildP( $tekst = '', $opts = array() ) {
        return $this->buildParagraph( $tekst, $opts );
    }

    public function buildPR( array $runs, $opts = [] ) {
        return $this->buildParagraphRuns( $runs, $opts );
    }

    // ── Helper: komórka z tekstem ─────────────────────────────────────────
    public static function tc( $text, $w, $opts = [] ) {
        return array_merge( [ 'text' => $text, 'w' => $w ], $opts );
    }

    // ── Helper: komórka z akapitami ───────────────────────────────────────
    public static function tcP( array $paragraphsXml, $w, $opts = [] ) {
        return array_merge( [ 'paragraphs' => $paragraphsXml, 'w' => $w ], $opts );
    }

    // ── Wrapper save z landscape ──────────────────────────────────────────
    public function saveLS( $filepath ) {
        $xml = $this->buildDocument( true );
        return $this->createZip( $filepath, $xml );
    }

    public function downloadLS( $filename ) {
        $tmp = tempnam( sys_get_temp_dir(), 'oe_docx_' );
        $this->saveLS( $tmp );
        header( 'Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . filesize( $tmp ) );
        header( 'Pragma: no-cache' );
        readfile( $tmp );
        @unlink( $tmp );
        exit;
    }
}
