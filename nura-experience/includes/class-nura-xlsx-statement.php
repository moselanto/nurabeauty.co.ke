<?php
/**
 * NURA Desk - professional Statement of Profit or Loss workbook (plugin 1.100.0).
 *
 * Owner (6 Nov 2026): "the Excel report should be very very professional, doing
 * all the calculations, international standards". Layout follows IAS 1
 * (expenses by nature): Revenue, Cost of sales, Gross profit, Operating
 * expenses, Operating profit, One-off (non-recurring) items, Profit before tax,
 * Income tax, Profit for the period - current period beside the comparative
 * prior period, variance (KES and %) and common-size (% of revenue).
 * Every subtotal, variance and ratio is a live Excel formula, so the workbook
 * recalculates if a figure is edited. Negatives in brackets, KES, A4 print.
 *
 * Sheets: 1 Profit or loss, 2 Key ratios, 3 Notes (basis of preparation and
 * items outside profit), 4 By product (when sales exist).
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Xlsx_Statement {

	/* style ids (cellXfs order in styles()) */
	const S_DEF = 0, S_BRAND = 1, S_TITLE = 2, S_SUB = 3, S_HEAD = 4, S_HEAD_R = 5, S_SEC = 6, S_LINE = 7, S_MONEY = 8, S_PCT = 9;
	const S_TOT = 10, S_TOT_M = 11, S_TOT_P = 12, S_NET = 13, S_NET_M = 14, S_NET_P = 15, S_NOTE = 16, S_NOTE_C = 17, S_SMALL = 18, S_INT = 19, S_GOLD = 20, S_KPI = 21;
	const S_DATE = 22, S_TXT = 23, S_MUTED = 24, S_ATTN = 25;

	private static function x( $v ) {
		return htmlspecialchars( (string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}

	private static function col( $i ) {
		$s = '';
		for ( $i++; $i > 0; $i = intdiv( $i - 1, 26 ) ) {
			$s = chr( 65 + ( $i - 1 ) % 26 ) . $s;
		}
		return $s;
	}

	private static function t( $ref, $s, $text ) {
		return '<c r="' . $ref . '" s="' . $s . '" t="inlineStr"><is><t xml:space="preserve">' . self::x( $text ) . '</t></is></c>';
	}

	private static function n( $ref, $s, $v ) {
		return '<c r="' . $ref . '" s="' . $s . '"><v>' . ( is_finite( (float) $v ) ? round( (float) $v, 2 ) : 0 ) . '</v></c>';
	}

	private static function f( $ref, $s, $formula ) {
		return '<c r="' . $ref . '" s="' . $s . '"><f>' . self::x( $formula ) . '</f></c>';
	}

	private static function e( $ref, $s ) {
		return '<c r="' . $ref . '" s="' . $s . '"/>';
	}

	public static function styles() {
		$fonts = array(
			'<font><sz val="10"/><color rgb="FF1F1F1F"/><name val="Calibri"/></font>',
			'<font><b/><sz val="18"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>',
			'<font><b/><sz val="14"/><color rgb="FF0E0E0E"/><name val="Calibri"/></font>',
			'<font><sz val="10"/><color rgb="FF6F6A62"/><name val="Calibri"/></font>',
			'<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>',
			'<font><b/><sz val="10"/><color rgb="FF0E0E0E"/><name val="Calibri"/></font>',
			'<font><i/><sz val="9"/><color rgb="FF6F6A62"/><name val="Calibri"/></font>',
			'<font><b/><sz val="11"/><color rgb="FF0E0E0E"/><name val="Calibri"/></font>',
			'<font><b/><sz val="10"/><color rgb="FF8A6D1F"/><name val="Calibri"/></font>',
		);
		$fills = array(
			'<fill><patternFill patternType="none"/></fill>',
			'<fill><patternFill patternType="gray125"/></fill>',
			'<fill><patternFill patternType="solid"><fgColor rgb="FF0E0E0E"/><bgColor indexed="64"/></patternFill></fill>',
			'<fill><patternFill patternType="solid"><fgColor rgb="FFF6EFE0"/><bgColor indexed="64"/></patternFill></fill>',
			'<fill><patternFill patternType="solid"><fgColor rgb="FFC9A24B"/><bgColor indexed="64"/></patternFill></fill>',
			'<fill><patternFill patternType="solid"><fgColor rgb="FFFAF8F4"/><bgColor indexed="64"/></patternFill></fill>',
		);
		$borders = array(
			'<border><left/><right/><top/><bottom/><diagonal/></border>',
			'<border><left/><right/><top style="thin"><color rgb="FF0E0E0E"/></top><bottom/><diagonal/></border>',
			'<border><left/><right/><top style="thin"><color rgb="FF0E0E0E"/></top><bottom style="double"><color rgb="FF0E0E0E"/></bottom><diagonal/></border>',
			'<border><left/><right/><top/><bottom style="hair"><color rgb="FFE3DCCD"/></bottom><diagonal/></border>',
		);
		// numFmt 164 accounting KES, 165 percent, 166 integer
		$numfmts = '<numFmts count="4"><numFmt numFmtId="164" formatCode="#,##0;(#,##0);&quot;-&quot;"/><numFmt numFmtId="165" formatCode="0.0%;(0.0%);&quot;-&quot;"/><numFmt numFmtId="166" formatCode="#,##0"/><numFmt numFmtId="167" formatCode="d mmm yyyy"/></numFmts>';
		$xf = function ( $font, $fill, $border, $num = 0, $h = '', $wrap = false, $indent = 0, $v = 'center' ) {
			$a = '<alignment vertical="' . $v . '"' . ( $h ? ' horizontal="' . $h . '"' : '' ) . ( $wrap ? ' wrapText="1"' : '' ) . ( $indent ? ' indent="' . $indent . '"' : '' ) . '/>';
			return '<xf numFmtId="' . $num . '" fontId="' . $font . '" fillId="' . $fill . '" borderId="' . $border . '" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"' . ( $num ? ' applyNumberFormat="1"' : '' ) . '>' . $a . '</xf>';
		};
		$x = array(
			$xf( 0, 0, 0 ),                        // 0 default
			$xf( 1, 2, 0, 0, 'left', false, 1 ),   // 1 brand band
			$xf( 2, 0, 0, 0, 'left' ),             // 2 title
			$xf( 3, 0, 0, 0, 'left' ),             // 3 sub
			$xf( 4, 2, 0, 0, 'left', true, 1 ),    // 4 head
			$xf( 4, 2, 0, 0, 'right', true ),      // 5 head right
			$xf( 8, 3, 0, 0, 'left', false, 1 ),   // 6 section
			$xf( 0, 0, 3, 0, 'left', false, 2 ),   // 7 line
			$xf( 0, 0, 3, 164, 'right' ),          // 8 money
			$xf( 3, 0, 3, 165, 'right' ),          // 9 pct
			$xf( 5, 5, 1, 0, 'left', false, 1 ),   // 10 subtotal text
			$xf( 5, 5, 1, 164, 'right' ),          // 11 subtotal money
			$xf( 5, 5, 1, 165, 'right' ),          // 12 subtotal pct
			$xf( 7, 3, 2, 0, 'left', false, 1 ),   // 13 net text
			$xf( 7, 3, 2, 164, 'right' ),          // 14 net money
			$xf( 7, 3, 2, 165, 'right' ),          // 15 net pct
			$xf( 0, 0, 3, 0, 'left', true, 1, 'top' ), // 16 note text wrap
			$xf( 3, 0, 3, 0, 'center' ),           // 17 note ref centre
			$xf( 6, 0, 0, 0, 'left', true ),       // 18 small italic
			$xf( 0, 0, 3, 166, 'right' ),          // 19 integer
			$xf( 0, 4, 0 ),                        // 20 gold strip
			$xf( 7, 0, 3, 0, 'left', false, 1 ),   // 21 kpi label
			$xf( 0, 0, 3, 167, 'left' ),           // 22 date (1.104.0)
			$xf( 0, 0, 3, 0, 'left', true ),       // 23 plain text, wraps (1.104.0)
			$xf( 3, 0, 3, 0, 'left', true ),       // 24 muted text, wraps (1.104.0)
			$xf( 0, 5, 3, 0, 'left', true ),       // 25 attention text on soft fill (1.104.0)
		);
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . $numfmts
			. '<fonts count="' . count( $fonts ) . '">' . implode( '', $fonts ) . '</fonts>'
			. '<fills count="' . count( $fills ) . '">' . implode( '', $fills ) . '</fills>'
			. '<borders count="' . count( $borders ) . '">' . implode( '', $borders ) . '</borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="' . count( $x ) . '">' . implode( '', $x ) . '</cellXfs>'
			. '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
	}

	/** Header band rows 1-6 shared by every sheet; returns [xml, merges, next row]. */
	private static function header( $biz, $title, $lines, $last ) {
		$xml   = '<row r="1" ht="40" customHeight="1">' . self::t( 'A1', self::S_BRAND, strtoupper( $biz['name'] ) ) . '</row>';
		$xml  .= '<row r="2" ht="4" customHeight="1">' . self::e( 'A2', self::S_GOLD ) . '</row>';
		$xml  .= '<row r="3" ht="28" customHeight="1">' . self::t( 'A3', self::S_TITLE, $title ) . '</row>';
		$r     = 4;
		$merge = array( 'A1:' . $last . '1', 'A2:' . $last . '2', 'A3:' . $last . '3' );
		foreach ( $lines as $l ) {
			$xml    .= '<row r="' . $r . '" ht="17" customHeight="1">' . self::t( 'A' . $r, self::S_SUB, $l ) . '</row>';
			$merge[] = 'A' . $r . ':' . $last . $r;
			$r++;
		}
		// band fill for row 1/2 across columns
		return array( $xml, $merge, $r + 1 );
	}

	private static function sheet_xml( $rows_xml, $cols, $merge, $last, $lastrow, $freeze, $biz, $title, $landscape = false ) {
		$mc = '';
		if ( $merge ) {
			$mc = '<mergeCells count="' . count( $merge ) . '">';
			foreach ( $merge as $m ) {
				$mc .= '<mergeCell ref="' . $m . '"/>';
			}
			$mc .= '</mergeCells>';
		}
		$pane = $freeze ? '<pane ySplit="' . ( $freeze - 1 ) . '" topLeftCell="A' . $freeze . '" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A' . $freeze . '" sqref="A' . $freeze . '"/>' : '';
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheetPr><tabColor rgb="FFC9A24B"/><pageSetUpPr fitToPage="1"/></sheetPr>'
			. '<dimension ref="A1:' . $last . $lastrow . '"/>'
			. '<sheetViews><sheetView showGridLines="0" zoomScale="110" zoomScaleNormal="110" workbookViewId="0">' . $pane . '</sheetView></sheetViews>'
			. '<sheetFormatPr defaultRowHeight="19" customHeight="1"/><cols>' . $cols . '</cols>'
			. '<sheetData>' . $rows_xml . '</sheetData>' . $mc
			. '<printOptions horizontalCentered="1"/><pageMargins left="0.5" right="0.5" top="0.6" bottom="0.7" header="0.3" footer="0.3"/>'
			. '<pageSetup paperSize="9" orientation="' . ( $landscape ? 'landscape' : 'portrait' ) . '" fitToWidth="1" fitToHeight="0"/>'
			. '<headerFooter><oddFooter>' . self::x( '&L&8' . $biz['name'] . ' - ' . $title . '&C&8Page &P of &N&R&8Prepared by NURA Desk' ) . '</oddFooter></headerFooter>'
			. '</worksheet>';
	}

	/**
	 * Build lines for one period from NURAX_Desk_Accounts::pnl() output.
	 * Returns array of sections: key => [label, [ [line label, amount, note], ... ] ].
	 */
	public static function lines( $r, $cats ) {
		$s   = $r['sales'];
		$out = array(
			'rev'   => array( 'Revenue', array(
				array( 'Website sales', (float) $s['online'], '2' ),
				array( 'In-shop sales (point of sale)', (float) $s['pos'], '2' ),
				array( 'Delivery charged to customers', (float) $s['shipping'], '2' ),
				array( 'Other operating income', (float) $r['other_income'], '2' ),
				array( 'Less: refunds and returns', -(float) $s['refunds'], '2' ),
			) ),
			'cogs'  => array( 'Cost of sales', array( array( 'Cost of goods sold (purchase cost of items sold)', -(float) $r['cogs_items'], '3' ) ) ),
			'opex'  => array( 'Operating expenses', array() ),
			'setup' => array( 'Non-recurring items (set-up costs)', array() ),
		);
		foreach ( $cats as $k => $c ) {
			if ( 'cogs' === $c[1] ) {
				$out['cogs'][1][] = array( $c[0], -(float) ( isset( $r['by'][ $k ] ) ? $r['by'][ $k ] : 0 ), '3', $k );
			} elseif ( 'opex' === $c[1] ) {
				$out['opex'][1][] = array( $c[0], -(float) ( isset( $r['by'][ $k ] ) ? $r['by'][ $k ] : 0 ), '4', $k );
			} elseif ( 'setup' === $c[1] ) {
				$out['setup'][1][] = array( $c[0], -(float) ( isset( $r['by'][ $k ] ) ? $r['by'][ $k ] : 0 ), '5', $k );
			}
		}
		$out['opex'][1][] = array( 'Rent (released from rent paid in advance)', -(float) ( isset( $r['by']['rent_advance'] ) ? $r['by']['rent_advance'] : 0 ), '6', 'rent_advance' );
		return $out;
	}

	/**
	 * Send the workbook.
	 *
	 * @param array $cur   pnl() for the period.
	 * @param array $prior pnl() for the comparative period.
	 * @param array $meta  from, to, label, pfrom, pto, plabel, cats.
	 */
	public static function send_pnl( $cur, $prior, $meta ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return false;
		}
		$biz  = class_exists( 'NURAX_Desk_POS' ) ? NURAX_Desk_POS::biz() : array( 'name' => 'NURA Beauty', 'web' => 'nurabeauty.co.ke', 'phone' => '+254 714 994 898', 'email' => 'care@nurabeauty.co.ke', 'pin' => '', 'addr' => '' );
		$cats = $meta['cats'];
		$lc   = self::lines( $cur, $cats );
		$lp   = self::lines( $prior, $cats );
		$fmtd = function ( $d ) {
			return function_exists( 'date_i18n' ) ? date_i18n( 'j M Y', strtotime( $d ) ) : gmdate( 'j M Y', strtotime( $d ) );
		};
		$cl = $meta['label'];
		$pl = $meta['plabel'];

		/* ---------------- Sheet 1: Statement of profit or loss ---------------- */
		$last = 'G';
		list( $xml, $merge, $r ) = self::header( $biz, 'Statement of Profit or Loss', array(
			'For the period ' . $fmtd( $meta['from'] ) . ' to ' . $fmtd( $meta['to'] ) . '  (comparative: ' . $fmtd( $meta['pfrom'] ) . ' to ' . $fmtd( $meta['pto'] ) . ')',
			'Presented in Kenya Shillings (KES). Expenses classified by nature (IAS 1). Amounts in brackets are costs or losses.',
			trim( ( $biz['pin'] ? 'KRA PIN ' . $biz['pin'] . '  |  ' : '' ) . 'Prepared ' . ( function_exists( 'wp_date' ) ? wp_date( 'j M Y, H:i' ) : gmdate( 'j M Y, H:i' ) ) . '  |  ' . $biz['web'] ),
		), $last );
		$h   = $r;
		$xml .= '<row r="' . $h . '" ht="32" customHeight="1">' . self::t( 'A' . $h, self::S_HEAD, 'LINE ITEM' ) . self::t( 'B' . $h, self::S_HEAD_R, 'NOTE' ) . self::t( 'C' . $h, self::S_HEAD_R, strtoupper( $cl ) . "\nKES" ) . self::t( 'D' . $h, self::S_HEAD_R, strtoupper( $pl ) . "\nKES" ) . self::t( 'E' . $h, self::S_HEAD_R, "CHANGE\nKES" ) . self::t( 'F' . $h, self::S_HEAD_R, "CHANGE\n%" ) . self::t( 'G' . $h, self::S_HEAD_R, "% OF\nREVENUE" ) . '</row>';
		$r     = $h + 1;
		$tot   = array();
		$revrow = 0;
		$line_row = function ( $label, $note, $c, $p, $style_t, $style_m, $style_p ) use ( &$xml, &$r, &$revrow ) {
			$xml .= '<row r="' . $r . '">' . self::t( 'A' . $r, $style_t, $label ) . ( '' !== $note ? self::t( 'B' . $r, self::S_NOTE_C, $note ) : self::e( 'B' . $r, self::S_NOTE_C ) )
				. ( is_string( $c ) ? self::f( 'C' . $r, $style_m, $c ) : self::n( 'C' . $r, $style_m, $c ) )
				. ( is_string( $p ) ? self::f( 'D' . $r, $style_m, $p ) : self::n( 'D' . $r, $style_m, $p ) )
				. self::f( 'E' . $r, $style_m, 'C' . $r . '-D' . $r )
				. self::f( 'F' . $r, $style_p, 'IF(D' . $r . '=0,"",(C' . $r . '-D' . $r . ')/ABS(D' . $r . '))' )
				. self::f( 'G' . $r, $style_p, $revrow ? 'IF($C$' . $revrow . '=0,"",C' . $r . '/$C$' . $revrow . ')' : '""' )
				. '</row>';
			$r++;
		};
		$section = function ( $key, $total_label ) use ( &$xml, &$r, $lc, $lp, $line_row, &$tot, &$revrow ) {
			$xml .= '<row r="' . $r . '" ht="22" customHeight="1">' . self::t( 'A' . $r, self::S_SEC, $lc[ $key ][0] ) . self::e( 'B' . $r, self::S_SEC ) . self::e( 'C' . $r, self::S_SEC ) . self::e( 'D' . $r, self::S_SEC ) . self::e( 'E' . $r, self::S_SEC ) . self::e( 'F' . $r, self::S_SEC ) . self::e( 'G' . $r, self::S_SEC ) . '</row>';
			$r++;
			$first = $r;
			foreach ( $lc[ $key ][1] as $i => $ln ) {
				$pv = isset( $lp[ $key ][1][ $i ] ) ? $lp[ $key ][1][ $i ][1] : 0;
				if ( 0.0 === round( (float) $ln[1], 2 ) && 0.0 === round( (float) $pv, 2 ) && 'rev' !== $key && 'cogs_items' !== ( isset( $ln[3] ) ? $ln[3] : '' ) && 0 !== $i ) {
					continue; // hide empty expense lines in both periods
				}
				$line_row( $ln[0], $ln[2], (float) $ln[1], (float) $pv, self::S_LINE, self::S_MONEY, self::S_PCT );
			}
			$lastl = $r - 1;
			$sum = $lastl >= $first ? 'SUM(C' . $first . ':C' . $lastl . ')' : '0';
			$sump = $lastl >= $first ? 'SUM(D' . $first . ':D' . $lastl . ')' : '0';
			$line_row( $total_label, '', $sum, $sump, self::S_TOT, self::S_TOT_M, self::S_TOT_P );
			$tot[ $key ] = $r - 1;
		};
		$revrow = $r + 1 + count( $lc['rev'][1] ); // total revenue row is known in advance (revenue lines are never hidden).
		$section( 'rev', 'Total revenue' );
		$section( 'cogs', 'Total cost of sales' );
		$line_row( 'GROSS PROFIT', '', 'C' . $tot['rev'] . '+C' . $tot['cogs'], 'D' . $tot['rev'] . '+D' . $tot['cogs'], self::S_TOT, self::S_TOT_M, self::S_TOT_P );
		$tot['gp'] = $r - 1;
		$section( 'opex', 'Total operating expenses' );
		$line_row( 'OPERATING PROFIT / (LOSS)', '', 'C' . $tot['gp'] . '+C' . $tot['opex'], 'D' . $tot['gp'] . '+D' . $tot['opex'], self::S_TOT, self::S_TOT_M, self::S_TOT_P );
		$tot['op'] = $r - 1;
		$section( 'setup', 'Total non-recurring items' );
		$line_row( 'PROFIT / (LOSS) BEFORE TAX', '', 'C' . $tot['op'] . '+C' . $tot['setup'], 'D' . $tot['op'] . '+D' . $tot['setup'], self::S_TOT, self::S_TOT_M, self::S_TOT_P );
		$tot['pbt'] = $r - 1;
		$line_row( 'Income tax expense', '7', 0, 0, self::S_LINE, self::S_MONEY, self::S_PCT );
		$tot['tax'] = $r - 1;
		$line_row( 'PROFIT / (LOSS) FOR THE PERIOD', '', 'C' . $tot['pbt'] . '+C' . $tot['tax'], 'D' . $tot['pbt'] . '+D' . $tot['tax'], self::S_NET, self::S_NET_M, self::S_NET_P );
		$tot['net'] = $r - 1;
		$r++;
		$xml    .= '<row r="' . $r . '" ht="30" customHeight="1">' . self::t( 'A' . $r, self::S_SMALL, 'The notes on the "Notes" sheet form part of this statement. Figures are generated from NURA Desk records (sales at the till and website, expenses and income entries); cost of sales uses the buying price recorded for each item sold. Unaudited management accounts.' ) . '</row>';
		$merge[] = 'A' . $r . ':G' . $r;
		$s1_last = $r;
		$cols1   = '<col min="1" max="1" width="50" customWidth="1"/><col min="2" max="2" width="7" customWidth="1"/><col min="3" max="5" width="17" customWidth="1"/><col min="6" max="7" width="11" customWidth="1"/>';
		$ws1     = self::sheet_xml( $xml, $cols1, $merge, $last, $s1_last, $h + 1, $biz, 'Statement of Profit or Loss' );
		$sn      = "'Profit or loss'!";

		/* ---------------- Sheet 2: Key ratios ---------------- */
		list( $xml, $merge, $r ) = self::header( $biz, 'Key performance ratios', array( 'Calculated live from the Statement of Profit or Loss.  ' . $cl . ' vs ' . $pl . '.' ), 'D' );
		$xml .= '<row r="' . $r . '" ht="30" customHeight="1">' . self::t( 'A' . $r, self::S_HEAD, 'RATIO' ) . self::t( 'B' . $r, self::S_HEAD_R, strtoupper( $cl ) ) . self::t( 'C' . $r, self::S_HEAD_R, strtoupper( $pl ) ) . self::t( 'D' . $r, self::S_HEAD, 'HOW IT IS CALCULATED' ) . '</row>';
		$r++;
		$rv  = $tot['rev'];
		$rat = array(
			array( 'Gross profit margin', 'pct', 'GP/Revenue', $tot['gp'] ),
			array( 'Operating profit margin', 'pct', 'Operating profit/Revenue', $tot['op'] ),
			array( 'Net profit margin', 'pct', 'Profit for the period/Revenue', $tot['net'] ),
			array( 'Cost of sales ratio', 'pctneg', 'Cost of sales/Revenue', $tot['cogs'] ),
			array( 'Operating expense ratio', 'pctneg', 'Operating expenses/Revenue', $tot['opex'] ),
		);
		foreach ( $rat as $q ) {
			$fc   = 'IF(' . $sn . '$C$' . $rv . '=0,"",' . ( 'pctneg' === $q[1] ? '-' : '' ) . $sn . 'C' . $q[3] . '/' . $sn . '$C$' . $rv . ')';
			$fp   = 'IF(' . $sn . '$D$' . $rv . '=0,"",' . ( 'pctneg' === $q[1] ? '-' : '' ) . $sn . 'D' . $q[3] . '/' . $sn . '$D$' . $rv . ')';
			$xml .= '<row r="' . $r . '">' . self::t( 'A' . $r, self::S_KPI, $q[0] ) . self::f( 'B' . $r, self::S_PCT, $fc ) . self::f( 'C' . $r, self::S_PCT, $fp ) . self::t( 'D' . $r, self::S_LINE, $q[2] ) . '</row>';
			$r++;
		}
		$fixed = array( 'Revenue growth vs comparative', 'IF(' . $sn . '$D$' . $rv . '=0,"",(' . $sn . '$C$' . $rv . '-' . $sn . '$D$' . $rv . ')/ABS(' . $sn . '$D$' . $rv . '))', 'S_PCT', '(Revenue now - revenue before)/revenue before' );
		$xml  .= '<row r="' . $r . '">' . self::t( 'A' . $r, self::S_KPI, $fixed[0] ) . self::f( 'B' . $r, self::S_PCT, $fixed[1] ) . self::e( 'C' . $r, self::S_PCT ) . self::t( 'D' . $r, self::S_LINE, $fixed[3] ) . '</row>';
		$r++;
		$be    = 'IF(OR(' . $sn . '$C$' . $rv . '=0,' . $sn . 'C' . $tot['gp'] . '<=0),"",-' . $sn . 'C' . $tot['opex'] . '/(' . $sn . 'C' . $tot['gp'] . '/' . $sn . '$C$' . $rv . '))';
		$bep   = 'IF(OR(' . $sn . '$D$' . $rv . '=0,' . $sn . 'D' . $tot['gp'] . '<=0),"",-' . $sn . 'D' . $tot['opex'] . '/(' . $sn . 'D' . $tot['gp'] . '/' . $sn . '$D$' . $rv . '))';
		$xml  .= '<row r="' . $r . '">' . self::t( 'A' . $r, self::S_KPI, 'Break-even revenue (KES)' ) . self::f( 'B' . $r, self::S_MONEY, $be ) . self::f( 'C' . $r, self::S_MONEY, $bep ) . self::t( 'D' . $r, self::S_LINE, 'Operating expenses / gross margin' ) . '</row>';
		$r++;
		$xml  .= '<row r="' . $r . '">' . self::t( 'A' . $r, self::S_KPI, 'Items sold (units)' ) . self::n( 'B' . $r, self::S_INT, (float) ( isset( $cur['sales']['qty'] ) ? $cur['sales']['qty'] : 0 ) ) . self::n( 'C' . $r, self::S_INT, (float) ( isset( $prior['sales']['qty'] ) ? $prior['sales']['qty'] : 0 ) ) . self::t( 'D' . $r, self::S_LINE, 'Units sold at the till and online' ) . '</row>';
		$s2_last = $r;
		$ws2     = self::sheet_xml( $xml, '<col min="1" max="1" width="34" customWidth="1"/><col min="2" max="3" width="18" customWidth="1"/><col min="4" max="4" width="46" customWidth="1"/>', $merge, 'D', $s2_last, 0, $biz, 'Key ratios' );

		/* ---------------- Sheet 3: Notes ---------------- */
		list( $xml, $merge, $r ) = self::header( $biz, 'Notes to the statement', array( 'Basis of preparation, accounting policies and items outside profit or loss.' ), 'D' );
		$notes = array(
			array( '1', 'Basis of preparation', 'Unaudited management accounts prepared from NURA Desk records on an accrual basis for sales (recognised when the order is paid or completed) and on the date entered for expenses. Presentation follows IAS 1 with expenses classified by nature. Currency: Kenya Shillings (KES).' ),
			array( '2', 'Revenue', 'Website and in-shop sales net of discounts, plus delivery charged to customers and other operating income (e.g. services paid outside the till). Refunds and returns are deducted in the period they are made. VAT, where charged, is excluded if recorded separately.' ),
			array( '3', 'Cost of sales', 'Purchase (buying) price of each item sold, captured at the time of sale, plus freight, import duty and packaging that are not already in the buying price. Stock bought is held as inventory and becomes cost of sales only when sold (IAS 2).' ),
			array( '4', 'Operating expenses', 'Running costs of the period by nature: rent, salaries and wages, utilities, marketing, delivery, payment charges, software, repairs, supplies, insurance, taxes and levies, and other expenses.' ),
			array( '5', 'Non-recurring items', 'One-off set-up costs (renovation and fit-out, legal and registration fees, licences and permits) shown separately so operating performance is comparable between periods.' ),
			array( '6', 'Prepaid rent', 'Rent paid in advance is a prepayment (asset) and is released to rent expense evenly over the months it covers. The unused balance at period end is shown in the memorandum below.' ),
			array( '7', 'Income tax', 'Income tax is not computed in these management accounts. Turnover tax or income tax is determined in the KRA return; county levies and taxes paid are included in operating expenses.' ),
		);
		$xml .= '<row r="' . $r . '" ht="26" customHeight="1">' . self::t( 'A' . $r, self::S_HEAD, 'NOTE' ) . self::t( 'B' . $r, self::S_HEAD, 'TOPIC' ) . self::t( 'C' . $r, self::S_HEAD, 'POLICY' ) . self::e( 'D' . $r, self::S_HEAD ) . '</row>';
		$merge[] = 'C' . $r . ':D' . $r;
		$r++;
		foreach ( $notes as $nt ) {
			$xml    .= '<row r="' . $r . '" ht="58" customHeight="1">' . self::t( 'A' . $r, self::S_NOTE_C, $nt[0] ) . self::t( 'B' . $r, self::S_KPI, $nt[1] ) . self::t( 'C' . $r, self::S_NOTE, $nt[2] ) . self::e( 'D' . $r, self::S_NOTE ) . '</row>';
			$merge[] = 'C' . $r . ':D' . $r;
			$r++;
		}
		$r++;
		$xml .= '<row r="' . $r . '" ht="26" customHeight="1">' . self::t( 'A' . $r, self::S_HEAD, '' ) . self::t( 'B' . $r, self::S_HEAD, 'MEMORANDUM - NOT PART OF PROFIT OR LOSS' ) . self::t( 'C' . $r, self::S_HEAD_R, strtoupper( $cl ) . ' KES' ) . self::t( 'D' . $r, self::S_HEAD_R, strtoupper( $pl ) . ' KES' ) . '</row>';
		$r++;
		$memo = array(
			array( 'Prepaid rent - unused balance at period end', 'advance_balance' ),
			array( 'Rent paid in advance during the period', 'prepaid' ),
			array( 'Stock purchased (inventory)', 'stock' ),
			array( 'Refundable deposits paid', 'deposit' ),
			array( 'Furniture and equipment purchased', 'equipment' ),
			array( 'Owner capital introduced', 'capital' ),
			array( 'Owner drawings', 'drawings' ),
		);
		$val = function ( $p, $k ) {
			if ( 'advance_balance' === $k ) {
				return isset( $p['advance']['balance'] ) ? (float) $p['advance']['balance'] : 0;
			}
			if ( 'prepaid' === $k ) {
				return isset( $p['prepaid_paid'] ) ? (float) $p['prepaid_paid'] : 0;
			}
			return isset( $p['by'][ $k ] ) ? (float) $p['by'][ $k ] : 0;
		};
		foreach ( $memo as $m ) {
			$xml .= '<row r="' . $r . '">' . self::e( 'A' . $r, self::S_LINE ) . self::t( 'B' . $r, self::S_LINE, $m[0] ) . self::n( 'C' . $r, self::S_MONEY, $val( $cur, $m[1] ) ) . self::n( 'D' . $r, self::S_MONEY, $val( $prior, $m[1] ) ) . '</row>';
			$r++;
		}
		$s3_last = $r;
		$ws3     = self::sheet_xml( $xml, '<col min="1" max="1" width="8" customWidth="1"/><col min="2" max="2" width="44" customWidth="1"/><col min="3" max="4" width="30" customWidth="1"/>', $merge, 'D', $s3_last, 0, $biz, 'Notes' );

		/* ---------------- Sheet 4: By product ---------------- */
		$sheets = array( array( 'Profit or loss', $ws1 ), array( 'Key ratios', $ws2 ), array( 'Notes', $ws3 ) );
		$prods  = isset( $cur['sales']['products'] ) ? (array) $cur['sales']['products'] : array();
		if ( $prods ) {
			list( $xml, $merge, $r ) = self::header( $biz, 'Gross profit by product', array( $cl . ' - sales, cost of sales and gross margin per product.' ), 'F' );
			$hr   = $r;
			$xml .= '<row r="' . $r . '" ht="30" customHeight="1">' . self::t( 'A' . $r, self::S_HEAD, 'PRODUCT' ) . self::t( 'B' . $r, self::S_HEAD_R, 'UNITS' ) . self::t( 'C' . $r, self::S_HEAD_R, 'SALES KES' ) . self::t( 'D' . $r, self::S_HEAD_R, 'COST OF SALES KES' ) . self::t( 'E' . $r, self::S_HEAD_R, 'GROSS PROFIT KES' ) . self::t( 'F' . $r, self::S_HEAD_R, 'MARGIN' ) . '</row>';
			$r++;
			$f0 = $r;
			foreach ( $prods as $p ) {
				$xml .= '<row r="' . $r . '">' . self::t( 'A' . $r, self::S_LINE, $p['name'] . ( ! empty( $p['missing'] ) ? '  (buying price missing)' : '' ) ) . self::n( 'B' . $r, self::S_INT, (float) $p['qty'] ) . self::n( 'C' . $r, self::S_MONEY, (float) $p['rev'] ) . self::n( 'D' . $r, self::S_MONEY, -(float) $p['cost'] ) . self::f( 'E' . $r, self::S_MONEY, 'C' . $r . '+D' . $r ) . self::f( 'F' . $r, self::S_PCT, 'IF(C' . $r . '=0,"",E' . $r . '/C' . $r . ')' ) . '</row>';
				$r++;
			}
			$l0   = $r - 1;
			$xml .= '<row r="' . $r . '" ht="22" customHeight="1">' . self::t( 'A' . $r, self::S_TOT, 'TOTAL' ) . self::f( 'B' . $r, self::S_TOT_M, 'SUM(B' . $f0 . ':B' . $l0 . ')' ) . self::f( 'C' . $r, self::S_TOT_M, 'SUM(C' . $f0 . ':C' . $l0 . ')' ) . self::f( 'D' . $r, self::S_TOT_M, 'SUM(D' . $f0 . ':D' . $l0 . ')' ) . self::f( 'E' . $r, self::S_TOT_M, 'SUM(E' . $f0 . ':E' . $l0 . ')' ) . self::f( 'F' . $r, self::S_TOT_P, 'IF(C' . $r . '=0,"",E' . $r . '/C' . $r . ')' ) . '</row>';
			$ws4      = self::sheet_xml( $xml, '<col min="1" max="1" width="48" customWidth="1"/><col min="2" max="2" width="9" customWidth="1"/><col min="3" max="5" width="18" customWidth="1"/><col min="6" max="6" width="10" customWidth="1"/>', $merge, 'F', $r, $hr + 1, $biz, 'Gross profit by product' );
			$sheets[] = array( 'By product', $ws4 );
		}

		return self::zip_send( $sheets, $biz, 'profit-or-loss-statement_' . $meta['from'] . '_to_' . $meta['to'] );
	}

	/**
	 * 1.104.0 (owner, 8 Nov 2026): the Expenses & income Excel report brought up to the
	 * standard of the Statement of Profit or Loss. Sheets:
	 *  1 Summary   - every category grouped by how it is treated (income, cost of sales,
	 *                operating, one-off set-up, then items outside profit or loss), current
	 *                period beside the comparative period, change KES / %, share of money
	 *                spent, number of entries; totals charged to profit, paid out in total,
	 *                net cash movement. Current-period figures are live SUMIFS on the register.
	 *  2 Register  - every entry in date order, numbered, filterable, with a live total.
	 *  3 Suppliers & methods - money paid per supplier and per payment method (live).
	 *  4 Notes     - how each type is treated, and records that need attention
	 *                (no reference, no receipt, rent advance without months).
	 *
	 * @param array $cur   entries() rows for the period.
	 * @param array $prior entries() rows for the comparative period.
	 * @param array $meta  from, to, label, pfrom, pto, plabel, cats, groups, methods.
	 */
	public static function send_entries( $cur, $prior, $meta, $path = '' ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return false;
		}
		$biz  = class_exists( 'NURAX_Desk_POS' ) ? NURAX_Desk_POS::biz() : array( 'name' => 'NURA Beauty', 'web' => 'nurabeauty.co.ke', 'phone' => '+254 714 994 898', 'email' => 'care@nurabeauty.co.ke', 'pin' => '', 'addr' => '' );
		$cats = $meta['cats'];
		$grps = $meta['groups'];
		$mth  = $meta['methods'];
		$cl   = $meta['label'];
		$pl   = $meta['plabel'];
		$fmtd = function ( $d ) {
			return function_exists( 'date_i18n' ) ? date_i18n( 'j M Y', strtotime( $d ) ) : gmdate( 'j M Y', strtotime( $d ) );
		};
		$prep  = 'Prepared ' . ( function_exists( 'wp_date' ) ? wp_date( 'j M Y, H:i' ) : gmdate( 'j M Y, H:i' ) ) . '  |  ' . $biz['web'];
		$gof   = function ( $k ) use ( $cats ) {
			return isset( $cats[ $k ] ) ? $cats[ $k ][1] : 'opex';
		};
		$label = function ( $k ) use ( $cats ) {
			return isset( $cats[ $k ] ) ? $cats[ $k ][0] : (string) $k;
		};
		$serial = function ( $d ) {
			$t = strtotime( $d . ' 00:00:00 UTC' );
			return $t ? floor( $t / 86400 ) + 25569 : 0;
		};
		$rows = array_values( (array) $cur );
		usort( $rows, function ( $a, $b ) {
			return strcmp( $a->entry_date . sprintf( '%09d', $a->id ), $b->entry_date . sprintf( '%09d', $b->id ) );
		} );
		$ptot = array();
		foreach ( (array) $prior as $e ) {
			$ptot[ $e->category ] = ( isset( $ptot[ $e->category ] ) ? $ptot[ $e->category ] : 0 ) + (float) $e->amount;
		}
		$ccount = array();
		foreach ( $rows as $e ) {
			$ccount[ $e->category ] = ( isset( $ccount[ $e->category ] ) ? $ccount[ $e->category ] : 0 ) + 1;
		}

		/* ---------------- Sheet 2 first (Summary formulas point at it): Register ---------------- */
		$last = 'K';
		list( $xml, $merge, $r ) = self::header( $biz, 'Register of expenses and income', array(
			'Period ' . $fmtd( $meta['from'] ) . ' to ' . $fmtd( $meta['to'] ) . '  |  ' . count( $rows ) . ' records, in date order. Amounts in Kenya Shillings (KES).',
			$prep,
		), $last );
		$rh   = $r;
		$heads = array( 'DATE', 'VOUCHER', 'TYPE', 'CATEGORY', 'DESCRIPTION', 'PAID TO / RECEIVED FROM', 'PAID BY', 'REFERENCE', 'AMOUNT KES', 'NOTE', 'RECEIPT' );
		$xml .= '<row r="' . $rh . '" ht="30" customHeight="1">';
		foreach ( $heads as $i => $hd ) {
			$xml .= self::t( self::col( $i ) . $rh, 'AMOUNT KES' === $hd ? self::S_HEAD_R : self::S_HEAD, $hd );
		}
		$xml .= '</row>';
		$r    = $rh + 1;
		$f0   = $r;
		foreach ( $rows as $n => $e ) {
			$g    = $gof( $e->category );
			$note = trim( (string) $e->note . ( 'prepaid' === $e->category && class_exists( 'NURAX_Desk_Accounts' ) ? ' ' . NURAX_Desk_Accounts::cover_text( $e ) : '' ) );
			$rcpt = ( ! empty( $e->receipt_id ) && function_exists( 'wp_get_attachment_url' ) ) ? (string) wp_get_attachment_url( $e->receipt_id ) : '';
			$xml .= '<row r="' . $r . '">'
				. self::n( 'A' . $r, self::S_DATE, $serial( $e->entry_date ) )
				. ( ! empty( $e->txn ) ? self::t( 'B' . $r, self::S_TXT, (string) $e->txn ) : self::n( 'B' . $r, self::S_INT, $n + 1 ) )
				. self::t( 'C' . $r, self::S_TXT, isset( $grps[ $g ] ) ? $grps[ $g ] : $g )
				. self::t( 'D' . $r, self::S_TXT, $label( $e->category ) )
				. self::t( 'E' . $r, self::S_TXT, (string) $e->description )
				. self::t( 'F' . $r, self::S_TXT, (string) $e->payee )
				. self::t( 'G' . $r, self::S_TXT, isset( $mth[ $e->method ] ) ? $mth[ $e->method ] : (string) $e->method )
				. self::t( 'H' . $r, self::S_TXT, (string) $e->ref )
				. self::n( 'I' . $r, self::S_MONEY, (float) $e->amount )
				. self::t( 'J' . $r, self::S_MUTED, $note )
				. self::t( 'K' . $r, self::S_MUTED, $rcpt ? $rcpt : '' )
				. '</row>';
			$r++;
		}
		$l0 = max( $f0, $r - 1 );
		if ( ! $rows ) {
			$xml .= '<row r="' . $r . '">' . self::t( 'A' . $r, self::S_MUTED, 'No records in this period.' ) . '</row>';
			$merge[] = 'A' . $r . ':' . $last . $r;
			$r++;
			$l0 = $r - 1;
		}
		$xml .= '<row r="' . $r . '" ht="22" customHeight="1">' . self::t( 'A' . $r, self::S_TOT, 'TOTAL OF ALL RECORDS (money in and out)' );
		foreach ( array( 'B', 'C', 'D', 'E', 'F', 'G', 'H' ) as $c ) {
			$xml .= self::e( $c . $r, self::S_TOT );
		}
		$xml .= self::f( 'I' . $r, self::S_TOT_M, 'SUBTOTAL(9,I' . $f0 . ':I' . $l0 . ')' ) . self::e( 'J' . $r, self::S_TOT ) . self::e( 'K' . $r, self::S_TOT ) . '</row>';
		$merge[] = 'A' . $r . ':H' . $r;
		$r++;
		$xml .= '<row r="' . $r . '" ht="28" customHeight="1">' . self::t( 'A' . $r, self::S_SMALL, 'Use the filter arrows on the header row to show one type, category, supplier or payment method; the total then adds up only the rows shown. See the Summary sheet for how each type is treated in the accounts.' ) . '</row>';
		$merge[] = 'A' . $r . ':' . $last . $r;
		$reg_last = $r;
		$cols = '<col min="1" max="1" width="12" customWidth="1"/><col min="2" max="2" width="10" customWidth="1"/><col min="3" max="3" width="30" customWidth="1"/><col min="4" max="4" width="30" customWidth="1"/><col min="5" max="5" width="40" customWidth="1"/><col min="6" max="6" width="26" customWidth="1"/><col min="7" max="7" width="13" customWidth="1"/><col min="8" max="8" width="14" customWidth="1"/><col min="9" max="9" width="15" customWidth="1"/><col min="10" max="10" width="40" customWidth="1"/><col min="11" max="11" width="24" customWidth="1"/>';
		$ws_reg = self::sheet_xml( $xml, $cols, $merge, $last, $reg_last, $rh + 1, $biz, 'Register of expenses and income', true );
		$ws_reg = str_replace( '</sheetData>', '</sheetData><autoFilter ref="A' . $rh . ':K' . $l0 . '"/>', $ws_reg );
		$R  = "Register!";
		$rc = $R . '$D$' . $f0 . ':$D$' . $l0;
		$ra = $R . '$I$' . $f0 . ':$I$' . $l0;
		$rp = $R . '$F$' . $f0 . ':$F$' . $l0;
		$rm = $R . '$G$' . $f0 . ':$G$' . $l0;
		$rt = $R . '$C$' . $f0 . ':$C$' . $l0;

		/* ---------------- Sheet 1: Summary ---------------- */
		$last = 'G';
		list( $xml, $merge, $r ) = self::header( $biz, 'Expenses and income summary', array(
			'For the period ' . $fmtd( $meta['from'] ) . ' to ' . $fmtd( $meta['to'] ) . '  (comparative: ' . $fmtd( $meta['pfrom'] ) . ' to ' . $fmtd( $meta['pto'] ) . ')',
			'Presented in Kenya Shillings (KES). Grouped by how each item is treated in the accounts (IAS 1 by nature). Sales from the till and website are in the Profit or Loss report, not here.',
			$prep,
		), $last );
		$h    = $r;
		$xml .= '<row r="' . $h . '" ht="32" customHeight="1">' . self::t( 'A' . $h, self::S_HEAD, 'CATEGORY' ) . self::t( 'B' . $h, self::S_HEAD_R, strtoupper( $cl ) . "\nKES" ) . self::t( 'C' . $h, self::S_HEAD_R, strtoupper( $pl ) . "\nKES" ) . self::t( 'D' . $h, self::S_HEAD_R, "CHANGE\nKES" ) . self::t( 'E' . $h, self::S_HEAD_R, "CHANGE\n%" ) . self::t( 'F' . $h, self::S_HEAD_R, "% OF MONEY\nPAID OUT" ) . self::t( 'G' . $h, self::S_HEAD_R, 'ENTRIES' ) . '</row>';
		$r    = $h + 1;
		$tot  = array();
		$outrow_ref = '__OUT__';
		$line = function ( $lab, $b, $c, $st, $sm, $sp, $cnt = null ) use ( &$xml, &$r, $outrow_ref ) {
			$xml .= '<row r="' . $r . '">' . self::t( 'A' . $r, $st, $lab )
				. ( is_string( $b ) ? self::f( 'B' . $r, $sm, $b ) : self::n( 'B' . $r, $sm, $b ) )
				. ( is_string( $c ) ? self::f( 'C' . $r, $sm, $c ) : self::n( 'C' . $r, $sm, $c ) )
				. self::f( 'D' . $r, $sm, 'B' . $r . '-C' . $r )
				. self::f( 'E' . $r, $sp, 'IF(C' . $r . '=0,"",(B' . $r . '-C' . $r . ')/ABS(C' . $r . '))' )
				. self::f( 'F' . $r, $sp, 'IF(' . $outrow_ref . '=0,"",B' . $r . '/' . $outrow_ref . ')' )
				. ( null === $cnt ? self::e( 'G' . $r, $sm ) : ( is_string( $cnt ) ? self::f( 'G' . $r, self::S_INT, $cnt ) : self::n( 'G' . $r, self::S_INT, $cnt ) ) )
				. '</row>';
			$r++;
		};
		$order = array(
			'income'  => array( 'Other income (money in, not from the till)', 'Total other income' ),
			'cogs'    => array( 'Cost of sales - extras', 'Total cost of sales extras' ),
			'opex'    => array( 'Operating expenses', 'Total operating expenses' ),
			'setup'   => array( 'One-off set-up costs (non-recurring)', 'Total one-off set-up costs' ),
			'prepaid' => array( 'Paid in advance (becomes rent month by month)', 'Total paid in advance' ),
			'stock'   => array( 'Stock bought (inventory)', 'Total stock bought' ),
			'asset'   => array( 'Assets and refundable deposits', 'Total assets and deposits' ),
			'owner'   => array( 'Owner capital and drawings', 'Total owner movements' ),
		);
		$secrow = function ( $text ) use ( &$xml, &$r ) {
			$xml .= '<row r="' . $r . '" ht="22" customHeight="1">' . self::t( 'A' . $r, self::S_SEC, $text );
			foreach ( array( 'B', 'C', 'D', 'E', 'F', 'G' ) as $c ) {
				$xml .= self::e( $c . $r, self::S_SEC );
			}
			$xml .= '</row>';
			$r++;
		};
		$catrows = array();
		$do_group = function ( $g ) use ( &$xml, &$r, $order, $cats, $ptot, $ccount, $rc, $ra, $line, $secrow, &$tot, &$catrows ) {
			$keys = array();
			foreach ( $cats as $k => $c ) {
				if ( $c[1] === $g && ( ! empty( $ccount[ $k ] ) || ! empty( $ptot[ $k ] ) ) ) {
					$keys[] = $k;
				}
			}
			$secrow( $order[ $g ][0] );
			$first = $r;
			foreach ( $keys as $k ) {
				$catrows[ $k ] = $r;
				$line( $cats[ $k ][0], 'SUMIFS(' . $ra . ',' . $rc . ',"' . str_replace( '"', '""', $cats[ $k ][0] ) . '")', isset( $ptot[ $k ] ) ? (float) $ptot[ $k ] : 0, self::S_LINE, self::S_MONEY, self::S_PCT, 'COUNTIFS(' . $rc . ',"' . str_replace( '"', '""', $cats[ $k ][0] ) . '")' );
			}
			if ( ! $keys ) {
				$xml .= '<row r="' . $r . '">' . self::t( 'A' . $r, self::S_MUTED, 'Nothing recorded in either period.' ) . '</row>';
				$r++;
			}
			$lastl = $r - 1;
			$b = $keys ? 'SUM(B' . $first . ':B' . $lastl . ')' : '0';
			$c = $keys ? 'SUM(C' . $first . ':C' . $lastl . ')' : '0';
			$n = $keys ? 'SUM(G' . $first . ':G' . $lastl . ')' : '0';
			$line( $order[ $g ][1], $b, $c, self::S_TOT, self::S_TOT_M, self::S_TOT_P, $n );
			$tot[ $g ] = $r - 1;
		};
		$secrow( 'A. MONEY IN' );
		$do_group( 'income' );
		$r++;
		$secrow( 'B. CHARGED TO PROFIT OR LOSS' );
		foreach ( array( 'cogs', 'opex', 'setup' ) as $g ) {
			$do_group( $g );
		}
		$line( 'TOTAL CHARGED TO PROFIT OR LOSS', 'B' . $tot['cogs'] . '+B' . $tot['opex'] . '+B' . $tot['setup'], 'C' . $tot['cogs'] . '+C' . $tot['opex'] . '+C' . $tot['setup'], self::S_NET, self::S_NET_M, self::S_NET_P, 'G' . $tot['cogs'] . '+G' . $tot['opex'] . '+G' . $tot['setup'] );
		$tot['pl'] = $r - 1;
		$r++;
		$secrow( 'C. NOT CHARGED TO PROFIT OR LOSS (balance sheet items)' );
		foreach ( array( 'prepaid', 'stock', 'asset', 'owner' ) as $g ) {
			$do_group( $g );
		}
		$r++;
		$secrow( 'D. CASH SUMMARY' );
		$dr   = isset( $catrows['drawings'] ) ? $catrows['drawings'] : 0;
		$cp   = isset( $catrows['capital'] ) ? $catrows['capital'] : 0;
		$outb = 'B' . $tot['pl'] . '+B' . $tot['prepaid'] . '+B' . $tot['stock'] . '+B' . $tot['asset'] . ( $dr ? '+B' . $dr : '' );
		$outc = 'C' . $tot['pl'] . '+C' . $tot['prepaid'] . '+C' . $tot['stock'] . '+C' . $tot['asset'] . ( $dr ? '+C' . $dr : '' );
		$line( 'Total money paid out (all records except money in and capital)', $outb, $outc, self::S_TOT, self::S_TOT_M, self::S_TOT_P );
		$tot['out'] = $r - 1;
		$inb = 'B' . $tot['income'] . ( $cp ? '+B' . $cp : '' );
		$inc = 'C' . $tot['income'] . ( $cp ? '+C' . $cp : '' );
		$line( 'Total money in (other income and owner capital)', $inb, $inc, self::S_TOT, self::S_TOT_M, self::S_TOT_P );
		$tot['in'] = $r - 1;
		$line( 'NET CASH MOVEMENT FROM THESE RECORDS', 'B' . $tot['in'] . '-B' . $tot['out'], 'C' . $tot['in'] . '-C' . $tot['out'], self::S_NET, self::S_NET_M, self::S_NET_P );
		$r++;
		$xml .= '<row r="' . $r . '" ht="44" customHeight="1">' . self::t( 'A' . $r, self::S_SMALL, 'Current-period figures are live formulas on the Register sheet, so they update if a record there is corrected. Money paid out is shown as a positive amount; net cash movement in brackets means more went out than came in. Rent paid in advance is not a cost until the months it covers (see the Profit or Loss report). Unaudited management records.' ) . '</row>';
		$merge[] = 'A' . $r . ':G' . $r;
		$sum_last = $r;
		$xml = str_replace( $outrow_ref, '$B$' . $tot['out'], $xml );
		$ws_sum = self::sheet_xml( $xml, '<col min="1" max="1" width="52" customWidth="1"/><col min="2" max="4" width="16" customWidth="1"/><col min="5" max="6" width="12" customWidth="1"/><col min="7" max="7" width="9" customWidth="1"/>', $merge, $last, $sum_last, $h + 1, $biz, 'Expenses and income summary' );

		/* ---------------- Sheet 3: Suppliers & methods ---------------- */
		$last = 'D';
		list( $xml, $merge, $r ) = self::header( $biz, 'Paid to and payment methods', array( $cl . ' - money paid out per supplier and per payment method (live from the Register). Money in is excluded.' ), $last );
		$payees = array();
		$methods_used = array();
		foreach ( $rows as $e ) {
			$g = $gof( $e->category );
			if ( 'income' === $g || 'capital' === $e->category ) {
				continue;
			}
			$pn = trim( (string) $e->payee );
			$pn = '' === $pn ? '(not recorded)' : $pn;
			$payees[ $pn ] = ( isset( $payees[ $pn ] ) ? $payees[ $pn ] : 0 ) + (float) $e->amount;
			$mn = isset( $mth[ $e->method ] ) ? $mth[ $e->method ] : (string) $e->method;
			$methods_used[ $mn ] = true;
		}
		arsort( $payees );
		$excl = '"<>' . str_replace( '"', '""', isset( $grps['income'] ) ? $grps['income'] : 'income' ) . '",' . $rd = '';
		$table = function ( $title, $items, $kind ) use ( &$xml, &$r, &$merge, $ra, $rp, $rm, $rt, $rc, $grps, $cats ) {
			$xml .= '<row r="' . $r . '" ht="28" customHeight="1">' . self::t( 'A' . $r, self::S_HEAD, $title ) . self::t( 'B' . $r, self::S_HEAD_R, 'ENTRIES' ) . self::t( 'C' . $r, self::S_HEAD_R, 'PAID OUT KES' ) . self::t( 'D' . $r, self::S_HEAD_R, '% OF TOTAL' ) . '</row>';
			$r++;
			$f = $r;
			$inc = str_replace( '"', '""', isset( $grps['income'] ) ? $grps['income'] : 'Other income' );
			$cap = str_replace( '"', '""', isset( $cats['capital'] ) ? $cats['capital'][0] : 'Owner capital in' );
			$n   = count( $items );
			foreach ( $items as $name ) {
				$crit_rng = 'payee' === $kind ? $rp : $rm;
				$crit     = '(not recorded)' === $name ? '""' : '"' . str_replace( '"', '""', $name ) . '"';
				$xml .= '<row r="' . $r . '">' . self::t( 'A' . $r, self::S_LINE, $name )
					. self::f( 'B' . $r, self::S_INT, 'COUNTIFS(' . $crit_rng . ',' . $crit . ',' . $rt . ',"<>' . $inc . '",' . $rc . ',"<>' . $cap . '")' )
					. self::f( 'C' . $r, self::S_MONEY, 'SUMIFS(' . $ra . ',' . $crit_rng . ',' . $crit . ',' . $rt . ',"<>' . $inc . '",' . $rc . ',"<>' . $cap . '")' )
					. self::f( 'D' . $r, self::S_PCT, 'IF(SUM($C$' . $f . ':$C$' . ( $f + $n - 1 ) . ')=0,"",C' . $r . '/SUM($C$' . $f . ':$C$' . ( $f + $n - 1 ) . '))' )
					. '</row>';
				$r++;
			}
			$l = $r - 1;
			$xml .= '<row r="' . $r . '" ht="22" customHeight="1">' . self::t( 'A' . $r, self::S_TOT, 'TOTAL' ) . self::f( 'B' . $r, self::S_TOT_M, $n ? 'SUM(B' . $f . ':B' . $l . ')' : '0' ) . self::f( 'C' . $r, self::S_TOT_M, $n ? 'SUM(C' . $f . ':C' . $l . ')' : '0' ) . self::f( 'D' . $r, self::S_TOT_P, $n ? 'SUM(D' . $f . ':D' . $l . ')' : '0' ) . '</row>';
			$r += 2;
		};
		$table( 'PAID TO', array_keys( $payees ), 'payee' );
		$table( 'PAYMENT METHOD', array_keys( $methods_used ), 'method' );
		$xml .= '<row r="' . $r . '" ht="30" customHeight="1">' . self::t( 'A' . $r, self::S_SMALL, '"(not recorded)" means the supplier was left blank in NURA Desk - add it so spending per supplier is complete.' ) . '</row>';
		$merge[] = 'A' . $r . ':D' . $r;
		$ws_sup = self::sheet_xml( $xml, '<col min="1" max="1" width="44" customWidth="1"/><col min="2" max="2" width="10" customWidth="1"/><col min="3" max="3" width="18" customWidth="1"/><col min="4" max="4" width="12" customWidth="1"/>', $merge, $last, $r, 0, $biz, 'Paid to and payment methods' );

		/* ---------------- Sheet 4: Notes ---------------- */
		list( $xml, $merge, $r ) = self::header( $biz, 'Notes and records to check', array( 'How each type is treated, and records in this period that need attention.' ), 'D' );
		$xml .= '<row r="' . $r . '" ht="26" customHeight="1">' . self::t( 'A' . $r, self::S_HEAD, 'TYPE' ) . self::t( 'B' . $r, self::S_HEAD, 'TREATMENT IN THE ACCOUNTS' ) . self::e( 'C' . $r, self::S_HEAD ) . self::e( 'D' . $r, self::S_HEAD ) . '</row>';
		$merge[] = 'B' . $r . ':D' . $r;
		$r++;
		$treat = array(
			'income'  => 'Money received outside the till or website (e.g. a service paid by M-Pesa). Added to revenue as other operating income.',
			'cogs'    => 'Freight, duty and packaging not already in the buying price. Added to cost of sales in the month paid.',
			'opex'    => 'Running costs of the month by nature (rent, wages, utilities, marketing, delivery, charges, software, repairs, supplies, insurance, taxes). Charged to profit or loss in the month paid.',
			'setup'   => 'One-off costs of setting up the shop (fit-out, legal and lease fees, licences). Charged to profit or loss but shown separately as non-recurring so normal months compare fairly.',
			'prepaid' => 'Rent paid ahead (IAS 1 prepayment). An asset when paid; released to rent expense evenly over the months it covers.',
			'stock'   => 'Wigs, hair and products bought for resale (IAS 2 inventory). Becomes cost of sales only when each item is sold, at its buying price.',
			'asset'   => 'Furniture and equipment kept for years, and refundable deposits. Not a cost of the month.',
			'owner'   => 'Money the owner puts in or takes out. Equity movements, never income or expense.',
		);
		foreach ( $treat as $g => $tx ) {
			$xml .= '<row r="' . $r . '" ht="44" customHeight="1">' . self::t( 'A' . $r, self::S_KPI, isset( $grps[ $g ] ) ? $grps[ $g ] : $g ) . self::t( 'B' . $r, self::S_NOTE, $tx ) . self::e( 'C' . $r, self::S_NOTE ) . self::e( 'D' . $r, self::S_NOTE ) . '</row>';
			$merge[] = 'B' . $r . ':D' . $r;
			$r++;
		}
		$r++;
		$xml .= '<row r="' . $r . '" ht="26" customHeight="1">' . self::t( 'A' . $r, self::S_HEAD, 'RECORDS TO CHECK' ) . self::t( 'B' . $r, self::S_HEAD, 'DETAIL' ) . self::e( 'C' . $r, self::S_HEAD ) . self::t( 'D' . $r, self::S_HEAD_R, 'AMOUNT KES' ) . '</row>';
		$merge[] = 'B' . $r . ':C' . $r;
		$r++;
		$checks = 0;
		// 1.105.0: whole-payment totals (receipt rule) and references used on more than one voucher.
		$vtot = array();
		$rtx  = array();
		foreach ( $rows as $e ) {
			$k = ! empty( $e->txn ) ? $e->txn : 'id' . $e->id;
			$vtot[ $k ] = ( isset( $vtot[ $k ] ) ? $vtot[ $k ] : 0 ) + (float) $e->amount;
			$rf = strtoupper( preg_replace( '/\\s+/', '', (string) $e->ref ) );
			if ( '' !== $rf ) {
				$rtx[ $rf ][ $k ] = true;
			}
		}
		foreach ( $rows as $n => $e ) {
			$issues = array();
			$vk     = ! empty( $e->txn ) ? $e->txn : 'id' . $e->id;
			$rf     = strtoupper( preg_replace( '/\\s+/', '', (string) $e->ref ) );
			if ( '' !== $rf && count( $rtx[ $rf ] ) > 1 ) {
				$issues[] = 'same reference is on ' . count( $rtx[ $rf ] ) . ' different vouchers - check it is not recorded twice';
			}
			if ( 'mpesa' === $e->method && '' === trim( (string) $e->ref ) ) {
				$issues[] = 'M-Pesa payment without a transaction code';
			}
			if ( '' === trim( (string) $e->payee ) && 'income' !== $gof( $e->category ) ) {
				$issues[] = 'supplier not recorded';
			}
			if ( empty( $e->receipt_id ) && $vtot[ $vk ] >= 5000 ) {
				$issues[] = 'no receipt attached (payment of KSh 5,000 or more)';
			}
			if ( 'prepaid' === $e->category && ( empty( $e->cover_from ) ) ) {
				$issues[] = 'months it covers not set, so no rent is released';
			}
			if ( ! $issues ) {
				continue;
			}
			$checks++;
			$xml .= '<row r="' . $r . '" ht="30" customHeight="1">' . self::t( 'A' . $r, self::S_ATTN, ( ! empty( $e->txn ) ? $e->txn : 'No. ' . ( $n + 1 ) ) . ' - ' . $fmtd( $e->entry_date ) ) . self::t( 'B' . $r, self::S_ATTN, $e->description . ': ' . implode( '; ', $issues ) ) . self::e( 'C' . $r, self::S_ATTN ) . self::n( 'D' . $r, self::S_MONEY, (float) $e->amount ) . '</row>';
			$merge[] = 'B' . $r . ':C' . $r;
			$r++;
		}
		if ( ! $checks ) {
			$xml .= '<row r="' . $r . '">' . self::t( 'A' . $r, self::S_MUTED, 'Nothing to check - every record is complete.' ) . '</row>';
			$merge[] = 'A' . $r . ':D' . $r;
			$r++;
		}
		$ws_notes = self::sheet_xml( $xml, '<col min="1" max="1" width="36" customWidth="1"/><col min="2" max="2" width="44" customWidth="1"/><col min="3" max="3" width="20" customWidth="1"/><col min="4" max="4" width="16" customWidth="1"/>', $merge, 'D', $r, 0, $biz, 'Notes and records to check' );

		return self::zip_send( array( array( 'Summary', $ws_sum ), array( 'Register', $ws_reg ), array( 'Suppliers & methods', $ws_sup ), array( 'Notes', $ws_notes ) ), $biz, 'expenses-and-income-report_' . $meta['from'] . '_to_' . $meta['to'], $path );
	}

	/** Package sheets and stream the file (or write to $path when given, for tests). */
	public static function zip_send( $sheets, $biz, $slug, $path = '' ) {
		$n   = count( $sheets );
		$ct  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
		$wbs = '';
		$wr  = '';
		$dn  = '';
		foreach ( $sheets as $i => $s ) {
			$ct  .= '<Override PartName="/xl/worksheets/sheet' . ( $i + 1 ) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
			$wbs .= '<sheet name="' . self::x( $s[0] ) . '" sheetId="' . ( $i + 1 ) . '" r:id="rId' . ( $i + 1 ) . '"/>';
			$wr  .= '<Relationship Id="rId' . ( $i + 1 ) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ( $i + 1 ) . '.xml"/>';
		}
		$ct  .= '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>';
		$wr  .= '<Relationship Id="rId' . ( $n + 1 ) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
		$wb   = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView activeTab="0"/></bookViews><sheets>' . $wbs . '</sheets><calcPr calcId="191029" fullCalcOnLoad="1"/></workbook>';
		$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>';
		$now  = gmdate( 'Y-m-d\TH:i:s\Z' );
		$core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>' . self::x( $biz['name'] . ' - Statement of Profit or Loss' ) . '</dc:title><dc:creator>NURA Desk</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified></cp:coreProperties>';
		$app  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>NURA Desk</Application><Company>' . self::x( $biz['name'] ) . '</Company></Properties>';
		$tmp  = $path ? $path : ( function_exists( 'wp_tempnam' ) ? wp_tempnam( 'nura-pnl' ) : tempnam( sys_get_temp_dir(), 'nura' ) );
		$zip  = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return false;
		}
		$zip->addFromString( '[Content_Types].xml', $ct );
		$zip->addFromString( '_rels/.rels', $rels );
		$zip->addFromString( 'docProps/core.xml', $core );
		$zip->addFromString( 'docProps/app.xml', $app );
		$zip->addFromString( 'xl/workbook.xml', $wb );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $wr . '</Relationships>' );
		$zip->addFromString( 'xl/styles.xml', self::styles() );
		foreach ( $sheets as $i => $s ) {
			$zip->addFromString( 'xl/worksheets/sheet' . ( $i + 1 ) . '.xml', $s[1] );
		}
		$zip->close();
		if ( $path ) {
			return true;
		}
		$file = sanitize_file_name( strtolower( str_replace( ' ', '-', $biz['name'] ) ) . '-' . $slug . '.xlsx' );
		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="' . $file . '"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		wp_delete_file( $tmp );
		return true;
	}
}
