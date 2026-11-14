<?php
/**
 * NURA Desk - Book of Accounts workbook (plugin 1.112.0).
 *
 * Owner (14 Nov 2026): "improve the Excel workbook so we have profit and loss auto
 * calculation ... a complete book of account ... very professional, international
 * standards, well formatted, well balanced".
 *
 * A double-entry book built from NURA Desk data (paid website, till and WhatsApp
 * orders, cost of items sold, and every voucher in Expenses & income):
 *   1 Cover               period inputs (change the dates and every statement recalculates),
 *                         key figures, balance checks and contents
 *   2 Profit or loss      IAS 1, expenses by nature, current vs comparative period
 *   3 Financial position  assets = owner's equity + liabilities, with a balance check
 *   4 Cash flows          IAS 7 direct method, agreed to cash in the financial position
 *   5 Trial balance       every account, debits = credits check
 *   6 Journal             the general journal (one line per debit or credit) - the single source
 *                         every statement reads with SUMIFS formulas, so rows added here are
 *                         picked up automatically
 *   7 Accounts            chart of accounts with live balances
 *   8 Sales register      paid orders with method, reference, cost and gross profit
 *   9 Unpaid orders       ordered but not paid - not in the books until marked as paid
 *  10 Notes               basis of preparation and policies
 *
 * Styles and packaging are shared with NURAX_Xlsx_Statement.
 *
 * @package NURA_Experience
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NURAX_Xlsx_Book {

	/* style ids - same order as NURAX_Xlsx_Statement::styles() */
	const S_DEF = 0, S_BRAND = 1, S_TITLE = 2, S_SUB = 3, S_HEAD = 4, S_HEAD_R = 5, S_SEC = 6, S_LINE = 7, S_MONEY = 8, S_PCT = 9;
	const S_TOT = 10, S_TOT_M = 11, S_TOT_P = 12, S_NET = 13, S_NET_M = 14, S_NET_P = 15, S_NOTE = 16, S_NOTE_C = 17, S_SMALL = 18, S_INT = 19, S_GOLD = 20, S_KPI = 21;
	const S_DATE = 22, S_TXT = 23, S_MUTED = 24, S_ATTN = 25;

	const JMAX = 20000; // last journal row read by the formulas (room to add lines by hand).
	const J0   = 9;     // first journal data row.

	/* ------------------------------------------------------------ cell helpers */

	private static function x( $v ) {
		return htmlspecialchars( (string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
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

	/** Excel date serial for Y-m-d. */
	private static function serial( $d ) {
		$ts = strtotime( substr( (string) $d, 0, 10 ) . ' 00:00:00 UTC' );
		return $ts ? (int) floor( $ts / 86400 ) + 25569 : 0;
	}

	private static function fmtd( $d ) {
		return function_exists( 'date_i18n' ) ? date_i18n( 'j M Y', strtotime( $d ) ) : gmdate( 'j M Y', strtotime( $d ) );
	}

	/** Header band: brand, gold line, title, sub lines. Returns [xml, merges, next row]. */
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
		return array( $xml, $merge, $r + 1 );
	}

	private static function sheet( $rows_xml, $widths, $merge, $last, $lastrow, $freeze, $biz, $title, $landscape = false, $filter = '' ) {
		$cols = '';
		foreach ( $widths as $i => $w ) {
			$cols .= '<col min="' . ( $i + 1 ) . '" max="' . ( $i + 1 ) . '" width="' . $w . '" customWidth="1"/>';
		}
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
			. '<dimension ref="A1:' . $last . max( 1, $lastrow ) . '"/>'
			. '<sheetViews><sheetView showGridLines="0" zoomScale="110" zoomScaleNormal="110" workbookViewId="0">' . $pane . '</sheetView></sheetViews>'
			. '<sheetFormatPr defaultRowHeight="19" customHeight="1"/><cols>' . $cols . '</cols>'
			. '<sheetData>' . $rows_xml . '</sheetData>'
			. ( $filter ? '<autoFilter ref="' . $filter . '"/>' : '' )
			. $mc
			. '<printOptions horizontalCentered="1"/><pageMargins left="0.5" right="0.5" top="0.6" bottom="0.7" header="0.3" footer="0.3"/>'
			. '<pageSetup paperSize="9" orientation="' . ( $landscape ? 'landscape' : 'portrait' ) . '" fitToWidth="1" fitToHeight="0"/>'
			. '<headerFooter><oddFooter>' . self::x( '&L&8' . $biz['name'] . ' - ' . $title . '&C&8Page &P of &N&R&8Prepared by NURA Desk' ) . '</oddFooter></headerFooter>'
			. '</worksheet>';
	}

	/* ------------------------------------------------------------ formula builders */

	private static function rng( $col ) {
		return 'Journal!$' . $col . '$' . self::J0 . ':$' . $col . '$' . self::JMAX;
	}

	/**
	 * Debit minus credit for one account (or a code range "a-b") between two date cells
	 * (either may be '' for open-ended). $lt = true means strictly before $to.
	 */
	private static function dc( $code, $from, $to, $lt = false ) {
		$crit = array();
		if ( false !== strpos( (string) $code, '-' ) ) {
			list( $a, $b ) = explode( '-', $code );
			$crit[] = self::rng( 'E' ) . ',">=' . (int) $a . '"';
			$crit[] = self::rng( 'E' ) . ',"<=' . (int) $b . '"';
		} else {
			$crit[] = self::rng( 'E' ) . ',' . (int) $code;
		}
		if ( '' !== $from ) {
			$crit[] = self::rng( 'A' ) . ',">="&' . $from;
		}
		if ( '' !== $to ) {
			$crit[] = self::rng( 'A' ) . ',"' . ( $lt ? '<' : '<=' ) . '"&' . $to;
		}
		$c = implode( ',', $crit );
		return '(SUMIFS(' . self::rng( 'G' ) . ',' . $c . ')-SUMIFS(' . self::rng( 'H' ) . ',' . $c . '))';
	}

	/** Cash-flow line: debit minus credit on journal lines carrying this tag in the period. */
	private static function cf( $tag, $from, $to ) {
		$c = self::rng( 'I' ) . ',"' . str_replace( '"', '', $tag ) . '",' . self::rng( 'A' ) . ',">="&' . $from . ',' . self::rng( 'A' ) . ',"<="&' . $to;
		return '(SUMIFS(' . self::rng( 'G' ) . ',' . $c . ')-SUMIFS(' . self::rng( 'H' ) . ',' . $c . '))';
	}

	private static function sumrows( $rows, $col ) {
		if ( ! $rows ) {
			return '0';
		}
		return 'SUM(' . $col . min( $rows ) . ':' . $col . max( $rows ) . ')';
	}

	/* ------------------------------------------------------------ workbook */

	/**
	 * @param array  $book  from NURAX_Desk_Accounts::book_data().
	 * @param string $path  write to a file instead of streaming (tests).
	 */
	public static function send( $book, $path = '' ) {
		if ( ! class_exists( 'ZipArchive' ) || ! class_exists( 'NURAX_Xlsx_Statement' ) ) {
			return false;
		}
		$biz    = $book['biz'];
		$acc    = $book['accounts'];
		$from   = 'Cover!$C$9';
		$to     = 'Cover!$C$10';
		$pfrom  = 'Cover!$C$11';
		$pto    = 'Cover!$C$12';
		$cl     = $book['label'];
		$pl     = $book['plabel'];
		$sheets = array();

		/* ---------------- Journal ---------------- */
		list( $xml, $merge, $r ) = self::header( $biz, 'General Journal', array(
			'Double entry: every transaction has equal debits and credits. Every statement in this workbook is calculated from this sheet.',
			'To add a manual entry (e.g. a correction from your accountant) type it on a new row at the bottom - the statements update automatically. Use codes from the Accounts sheet.',
		), 'I' );
		$h    = self::J0 - 1;
		$xml .= '<row r="' . $h . '" ht="30" customHeight="1">' . self::t( 'A' . $h, self::S_HEAD, 'DATE' ) . self::t( 'B' . $h, self::S_HEAD, 'VOUCHER / REF' ) . self::t( 'C' . $h, self::S_HEAD, 'SOURCE' ) . self::t( 'D' . $h, self::S_HEAD, 'DESCRIPTION' ) . self::t( 'E' . $h, self::S_HEAD_R, 'ACCOUNT' ) . self::t( 'F' . $h, self::S_HEAD, 'ACCOUNT NAME' ) . self::t( 'G' . $h, self::S_HEAD_R, "DEBIT\nKES" ) . self::t( 'H' . $h, self::S_HEAD_R, "CREDIT\nKES" ) . self::t( 'I' . $h, self::S_HEAD, 'CASH FLOW LINE' ) . '</row>';
		$r = self::J0;
		foreach ( $book['journal'] as $j ) {
			$name = isset( $acc[ $j['code'] ] ) ? $acc[ $j['code'] ][0] : '';
			$xml .= '<row r="' . $r . '">' . self::n( 'A' . $r, self::S_DATE, self::serial( $j['date'] ) ) . self::t( 'B' . $r, self::S_TXT, $j['ref'] ) . self::t( 'C' . $r, self::S_MUTED, $j['src'] ) . self::t( 'D' . $r, self::S_TXT, $j['desc'] )
				. self::n( 'E' . $r, self::S_INT, $j['code'] ) . self::t( 'F' . $r, self::S_TXT, $name )
				. ( $j['dr'] ? self::n( 'G' . $r, self::S_MONEY, $j['dr'] ) : self::e( 'G' . $r, self::S_MONEY ) )
				. ( $j['cr'] ? self::n( 'H' . $r, self::S_MONEY, $j['cr'] ) : self::e( 'H' . $r, self::S_MONEY ) )
				. ( '' !== $j['cf'] ? self::t( 'I' . $r, self::S_MUTED, $j['cf'] ) : self::e( 'I' . $r, self::S_MUTED ) ) . '</row>';
			$r++;
		}
		if ( self::J0 === $r ) {
			$xml .= '<row r="' . $r . '">' . self::t( 'D' . $r, self::S_MUTED, 'No transactions yet.' ) . '</row>';
			$r++;
		}
		$jlast             = $r - 1;
		$sheets['Journal'] = self::sheet( $xml, array( 12, 15, 13, 46, 10, 30, 15, 15, 32 ), $merge, 'I', $jlast, self::J0, $biz, 'General Journal', true, 'A' . $h . ':I' . $jlast );

		/* ---------------- Accounts (chart of accounts) ---------------- */
		list( $xml, $merge, $r ) = self::header( $biz, 'Chart of Accounts', array( 'Balances at the end of the period on the Cover. Positive = debit balance, negative = credit balance.' ), 'F' );
		$h    = $r;
		$xml .= '<row r="' . $h . '" ht="30" customHeight="1">' . self::t( 'A' . $h, self::S_HEAD_R, 'CODE' ) . self::t( 'B' . $h, self::S_HEAD, 'ACCOUNT' ) . self::t( 'C' . $h, self::S_HEAD, 'CLASS' ) . self::t( 'D' . $h, self::S_HEAD, 'STATEMENT' ) . self::t( 'E' . $h, self::S_HEAD, 'NORMAL SIDE' ) . self::t( 'F' . $h, self::S_HEAD_R, "BALANCE\nKES" ) . '</row>';
		$r++;
		$classes = array( 'asset' => 'Asset', 'equity' => 'Equity', 'liability' => 'Liability', 'income' => 'Income', 'contra' => 'Income (contra)', 'cos' => 'Cost of sales', 'opex' => 'Operating expense', 'setup' => 'Non-recurring expense' );
		foreach ( $acc as $code => $a ) {
			$bs   = in_array( $a[1], array( 'asset', 'equity', 'liability' ), true );
			$xml .= '<row r="' . $r . '">' . self::n( 'A' . $r, self::S_INT, $code ) . self::t( 'B' . $r, self::S_TXT, $a[0] ) . self::t( 'C' . $r, self::S_MUTED, $classes[ $a[1] ] ) . self::t( 'D' . $r, self::S_MUTED, $bs ? 'Financial position' : 'Profit or loss' ) . self::t( 'E' . $r, self::S_MUTED, in_array( $a[1], array( 'equity', 'liability', 'income' ), true ) ? 'Credit' : 'Debit' ) . self::f( 'F' . $r, self::S_MONEY, self::dc( $code, '', $to ) ) . '</row>';
			$r++;
		}
		$sheets['Accounts'] = self::sheet( $xml, array( 10, 42, 22, 20, 14, 17 ), $merge, 'F', $r - 1, $h + 1, $biz, 'Chart of Accounts' );

		/* ---------------- Trial balance ---------------- */
		list( $xml, $merge, $r ) = self::header( $biz, 'Trial Balance', array( 'As at the end of the period on the Cover (all transactions from the start of the books). Presented in KES.' ), 'D' );
		$h    = $r;
		$xml .= '<row r="' . $h . '" ht="30" customHeight="1">' . self::t( 'A' . $h, self::S_HEAD_R, 'CODE' ) . self::t( 'B' . $h, self::S_HEAD, 'ACCOUNT' ) . self::t( 'C' . $h, self::S_HEAD_R, "DEBIT\nKES" ) . self::t( 'D' . $h, self::S_HEAD_R, "CREDIT\nKES" ) . '</row>';
		$r++;
		$t0 = $r;
		foreach ( $acc as $code => $a ) {
			$b    = self::dc( $code, '', $to );
			$xml .= '<row r="' . $r . '">' . self::n( 'A' . $r, self::S_INT, $code ) . self::t( 'B' . $r, self::S_TXT, $a[0] ) . self::f( 'C' . $r, self::S_MONEY, 'MAX(0,' . $b . ')' ) . self::f( 'D' . $r, self::S_MONEY, 'MAX(0,-' . $b . ')' ) . '</row>';
			$r++;
		}
		$t1      = $r - 1;
		$xml    .= '<row r="' . $r . '">' . self::t( 'A' . $r, self::S_NET, 'Totals' ) . self::e( 'B' . $r, self::S_NET ) . self::f( 'C' . $r, self::S_NET_M, 'SUM(C' . $t0 . ':C' . $t1 . ')' ) . self::f( 'D' . $r, self::S_NET_M, 'SUM(D' . $t0 . ':D' . $t1 . ')' ) . '</row>';
		$merge[] = 'A' . $r . ':B' . $r;
		$tbtot   = $r;
		$r      += 2;
		$xml    .= '<row r="' . $r . '">' . self::t( 'A' . $r, self::S_KPI, 'Check' ) . self::f( 'B' . $r, self::S_TXT, 'IF(ROUND(C' . $tbtot . '-D' . $tbtot . ',2)=0,"Balanced - debits equal credits","NOT BALANCED - difference "&TEXT(C' . $tbtot . '-D' . $tbtot . ',"#,##0.00"))' ) . '</row>';
		$merge[] = 'B' . $r . ':D' . $r;
		$tbchk   = $r;
		$sheets['Trial balance'] = self::sheet( $xml, array( 10, 46, 18, 18 ), $merge, 'D', $r, $h + 1, $biz, 'Trial Balance' );

		/* ---------------- Profit or loss ---------------- */
		list( $xml, $merge, $r ) = self::header( $biz, 'Statement of Profit or Loss', array(
			'For the period ' . self::fmtd( $book['from'] ) . ' to ' . self::fmtd( $book['to'] ) . '  (comparative: ' . self::fmtd( $book['pfrom'] ) . ' to ' . self::fmtd( $book['pto'] ) . ')',
			'Presented in Kenya Shillings (KES). Expenses classified by nature (IAS 1 / IFRS for SMEs). Amounts in brackets are costs or losses.',
			'Every figure is a live formula on the General Journal - change the dates on the Cover to recalculate.',
		), 'G' );
		$h    = $r;
		$xml .= '<row r="' . $h . '" ht="32" customHeight="1">' . self::t( 'A' . $h, self::S_HEAD, 'LINE ITEM' ) . self::t( 'B' . $h, self::S_HEAD_R, 'ACCOUNT' ) . self::t( 'C' . $h, self::S_HEAD_R, strtoupper( $cl ) . "\nKES" ) . self::t( 'D' . $h, self::S_HEAD_R, strtoupper( $pl ) . "\nKES" ) . self::t( 'E' . $h, self::S_HEAD_R, "CHANGE\nKES" ) . self::t( 'F' . $h, self::S_HEAD_R, "CHANGE\n%" ) . self::t( 'G' . $h, self::S_HEAD_R, "% OF\nREVENUE" ) . '</row>';
		$r      = $h + 1;
		$revtot = 0;
		$row    = function ( $label, $code, $fc, $fp, $st, $sm, $sp ) use ( &$xml, &$r, &$revtot ) {
			$xml .= '<row r="' . $r . '">' . self::t( 'A' . $r, $st, $label ) . ( '' !== $code ? self::n( 'B' . $r, self::S_INT, $code ) : self::e( 'B' . $r, self::S_NOTE_C ) )
				. self::f( 'C' . $r, $sm, $fc ) . self::f( 'D' . $r, $sm, $fp )
				. self::f( 'E' . $r, $sm, 'C' . $r . '-D' . $r )
				. self::f( 'F' . $r, $sp, 'IF(D' . $r . '=0,"",(C' . $r . '-D' . $r . ')/ABS(D' . $r . '))' )
				. self::f( 'G' . $r, $sp, $revtot ? 'IF($C$' . $revtot . '=0,"",C' . $r . '/$C$' . $revtot . ')' : 'IF(C' . $r . '=0,"",1)' )
				. '</row>';
			return $r++;
		};
		$sec = function ( $label ) use ( &$xml, &$r, &$merge ) {
			$xml    .= '<row r="' . $r . '" ht="22" customHeight="1">' . self::t( 'A' . $r, self::S_SEC, $label ) . '</row>';
			$merge[] = 'A' . $r . ':G' . $r;
			$r++;
		};
		$group = function ( $types ) use ( $acc, $row, $from, $to, $pfrom, $pto ) {
			$rows = array();
			foreach ( $acc as $code => $a ) {
				if ( in_array( $a[1], (array) $types, true ) ) {
					$rows[] = $row( $a[0], $code, '-' . self::dc( $code, $from, $to ), '-' . self::dc( $code, $pfrom, $pto ), self::S_LINE, self::S_MONEY, self::S_PCT );
				}
			}
			return $rows;
		};
		$sec( 'Revenue' );
		$rr     = $group( array( 'income', 'contra' ) );
		$revtot = $row( 'Revenue', '', self::sumrows( $rr, 'C' ), self::sumrows( $rr, 'D' ), self::S_TOT, self::S_TOT_M, self::S_TOT_P );
		$sec( 'Cost of sales' );
		$cr   = $group( 'cos' );
		$cost = $row( 'Total cost of sales', '', self::sumrows( $cr, 'C' ), self::sumrows( $cr, 'D' ), self::S_TOT, self::S_TOT_M, self::S_TOT_P );
		$gp   = $row( 'Gross profit', '', 'C' . $revtot . '+C' . $cost, 'D' . $revtot . '+D' . $cost, self::S_NET, self::S_NET_M, self::S_NET_P );
		$sec( 'Operating expenses' );
		$or   = $group( 'opex' );
		$opex = $row( 'Total operating expenses', '', self::sumrows( $or, 'C' ), self::sumrows( $or, 'D' ), self::S_TOT, self::S_TOT_M, self::S_TOT_P );
		$op   = $row( 'Operating profit / (loss)', '', 'C' . $gp . '+C' . $opex, 'D' . $gp . '+D' . $opex, self::S_NET, self::S_NET_M, self::S_NET_P );
		$sec( 'Non-recurring items (set-up costs)' );
		$sr   = $group( 'setup' );
		$setp = $row( 'Total non-recurring items', '', self::sumrows( $sr, 'C' ), self::sumrows( $sr, 'D' ), self::S_TOT, self::S_TOT_M, self::S_TOT_P );
		$pbt  = $row( 'Profit / (loss) before tax', '', 'C' . $op . '+C' . $setp, 'D' . $op . '+D' . $setp, self::S_TOT, self::S_TOT_M, self::S_TOT_P );
		$tax  = $row( 'Income tax expense (turnover tax is in operating expenses)', '', '0', '0', self::S_LINE, self::S_MONEY, self::S_PCT );
		$net  = $row( 'Profit / (loss) for the period', '', 'C' . $pbt . '+C' . $tax, 'D' . $pbt . '+D' . $tax, self::S_NET, self::S_NET_M, self::S_NET_P );
		$r++;
		$xml    .= '<row r="' . $r . '" ht="30" customHeight="1">' . self::t( 'A' . $r, self::S_SMALL, 'Revenue is recognised when the customer has paid (see Notes). Orders not yet paid are listed on the Unpaid orders sheet and are not in revenue.' ) . '</row>';
		$merge[] = 'A' . $r . ':G' . $r;
		$sheets['Profit or loss'] = self::sheet( $xml, array( 52, 9, 17, 17, 15, 11, 11 ), $merge, 'G', $r, $h + 1, $biz, 'Statement of Profit or Loss' );
		$PL = "'Profit or loss'!";

		/* ---------------- Financial position ---------------- */
		list( $xml, $merge, $r ) = self::header( $biz, 'Statement of Financial Position', array(
			'As at ' . self::fmtd( $book['to'] ) . '  (comparative: as at ' . self::fmtd( $book['pto'] ) . '). Presented in KES.',
			'Assets = Owner\'s equity + Liabilities. Retained profit is the total of all profit or loss from the start of the books.',
		), 'D' );
		$h    = $r;
		$xml .= '<row r="' . $h . '" ht="32" customHeight="1">' . self::t( 'A' . $h, self::S_HEAD, 'LINE ITEM' ) . self::t( 'B' . $h, self::S_HEAD_R, 'ACCOUNT' ) . self::t( 'C' . $h, self::S_HEAD_R, 'AS AT ' . strtoupper( self::fmtd( $book['to'] ) ) . "\nKES" ) . self::t( 'D' . $h, self::S_HEAD_R, 'AS AT ' . strtoupper( self::fmtd( $book['pto'] ) ) . "\nKES" ) . '</row>';
		$r    = $h + 1;
		$brow = function ( $label, $code, $fc, $fp, $st, $sm ) use ( &$xml, &$r ) {
			$xml .= '<row r="' . $r . '">' . self::t( 'A' . $r, $st, $label ) . ( '' !== $code ? self::n( 'B' . $r, self::S_INT, $code ) : self::e( 'B' . $r, self::S_NOTE_C ) ) . self::f( 'C' . $r, $sm, $fc ) . self::f( 'D' . $r, $sm, $fp ) . '</row>';
			return $r++;
		};
		$bsec = function ( $label ) use ( &$xml, &$r, &$merge ) {
			$xml    .= '<row r="' . $r . '" ht="22" customHeight="1">' . self::t( 'A' . $r, self::S_SEC, $label ) . '</row>';
			$merge[] = 'A' . $r . ':D' . $r;
			$r++;
		};
		$bgroup = function ( $codes, $sign ) use ( $acc, $brow, $to, $pto ) {
			$rows = array();
			foreach ( $codes as $code ) {
				if ( isset( $acc[ $code ] ) ) {
					$rows[] = $brow( $acc[ $code ][0], $code, $sign . self::dc( $code, '', $to ), $sign . self::dc( $code, '', $pto ), self::S_LINE, self::S_MONEY );
				}
			}
			return $rows;
		};
		$bsec( 'Non-current assets' );
		$nca  = $bgroup( array( 1500, 1300 ), '' );
		$ncat = $brow( 'Total non-current assets', '', self::sumrows( $nca, 'C' ), self::sumrows( $nca, 'D' ), self::S_TOT, self::S_TOT_M );
		$bsec( 'Current assets' );
		$ca  = $bgroup( array( 1100, 1200, 1010, 1020, 1030, 1040 ), '' );
		$cat = $brow( 'Total current assets', '', self::sumrows( $ca, 'C' ), self::sumrows( $ca, 'D' ), self::S_TOT, self::S_TOT_M );
		$ta  = $brow( 'TOTAL ASSETS', '', 'C' . $ncat . '+C' . $cat, 'D' . $ncat . '+D' . $cat, self::S_NET, self::S_NET_M );
		$r++;
		$bsec( 'Owner\'s equity' );
		$eq   = $bgroup( array( 3000, 3100 ), '-' );
		$eq[] = $brow( 'Retained profit / (accumulated loss)', '', '-' . self::dc( '4000-7999', '', $to ), '-' . self::dc( '4000-7999', '', $pto ), self::S_LINE, self::S_MONEY );
		$teq  = $brow( 'Total owner\'s equity', '', self::sumrows( $eq, 'C' ), self::sumrows( $eq, 'D' ), self::S_TOT, self::S_TOT_M );
		$bsec( 'Liabilities' );
		$li  = $bgroup( array( 2000 ), '-' );
		$tli = $brow( 'Total liabilities', '', self::sumrows( $li, 'C' ), self::sumrows( $li, 'D' ), self::S_TOT, self::S_TOT_M );
		$tel = $brow( 'TOTAL EQUITY AND LIABILITIES', '', 'C' . $teq . '+C' . $tli, 'D' . $teq . '+D' . $tli, self::S_NET, self::S_NET_M );
		$r++;
		$xml    .= '<row r="' . $r . '">' . self::t( 'A' . $r, self::S_KPI, 'Check' ) . self::f( 'B' . $r, self::S_TXT, 'IF(AND(ROUND(C' . $ta . '-C' . $tel . ',2)=0,ROUND(D' . $ta . '-D' . $tel . ',2)=0),"Balanced - assets equal equity and liabilities","NOT BALANCED - difference "&TEXT(C' . $ta . '-C' . $tel . ',"#,##0.00"))' ) . '</row>';
		$merge[] = 'B' . $r . ':D' . $r;
		$sfpchk  = $r;
		$r++;
		$xml    .= '<row r="' . $r . '" ht="30" customHeight="1">' . self::t( 'A' . $r, self::S_SMALL, 'A negative cash balance means payments were recorded from a method with no money recorded in. Record the owner\'s money put in as "Owner capital in" with the same method.' ) . '</row>';
		$merge[] = 'A' . $r . ':D' . $r;
		$sheets['Financial position'] = self::sheet( $xml, array( 52, 9, 20, 20 ), $merge, 'D', $r, $h + 1, $biz, 'Statement of Financial Position' );
		$SFP    = "'Financial position'!";
		$cash_c = self::dc( '1010-1040', '', $to );

		/* ---------------- Cash flows ---------------- */
		list( $xml, $merge, $r ) = self::header( $biz, 'Statement of Cash Flows', array(
			'For the period ' . self::fmtd( $book['from'] ) . ' to ' . self::fmtd( $book['to'] ) . ' (comparative: ' . self::fmtd( $book['pfrom'] ) . ' to ' . self::fmtd( $book['pto'] ) . '). Direct method (IAS 7). KES.',
			'Cash and cash equivalents = cash on hand, M-Pesa till, bank and Paystack (card / online M-Pesa) balances.',
		), 'D' );
		$h    = $r;
		$xml .= '<row r="' . $h . '" ht="32" customHeight="1">' . self::t( 'A' . $h, self::S_HEAD, 'LINE ITEM' ) . self::e( 'B' . $h, self::S_HEAD ) . self::t( 'C' . $h, self::S_HEAD_R, strtoupper( $cl ) . "\nKES" ) . self::t( 'D' . $h, self::S_HEAD_R, strtoupper( $pl ) . "\nKES" ) . '</row>';
		$r    = $h + 1;
		$tots = array();
		foreach ( self::cf_lines() as $act => $tags ) {
			$bsec( $act );
			$rows = array();
			foreach ( $tags as $tg ) {
				$rows[] = $brow( $tg, '', self::cf( $tg, $from, $to ), self::cf( $tg, $pfrom, $pto ), self::S_LINE, self::S_MONEY );
			}
			$tots[] = $brow( 'Net cash from ' . strtolower( $act ), '', self::sumrows( $rows, 'C' ), self::sumrows( $rows, 'D' ), self::S_TOT, self::S_TOT_M );
		}
		$r++;
		$netc = $brow( 'Net increase / (decrease) in cash', '', 'C' . implode( '+C', $tots ), 'D' . implode( '+D', $tots ), self::S_TOT, self::S_TOT_M );
		$open = $brow( 'Cash at the start of the period', '', self::dc( '1010-1040', '', $from, true ), self::dc( '1010-1040', '', $pfrom, true ), self::S_LINE, self::S_MONEY );
		$clos = $brow( 'Cash at the end of the period', '', 'C' . $netc . '+C' . $open, 'D' . $netc . '+D' . $open, self::S_NET, self::S_NET_M );
		$r++;
		$xml    .= '<row r="' . $r . '">' . self::t( 'A' . $r, self::S_KPI, 'Check' ) . self::f( 'B' . $r, self::S_TXT, 'IF(ROUND(C' . $clos . '-' . $cash_c . ',2)=0,"Agrees to cash in the Statement of Financial Position","DOES NOT AGREE - difference "&TEXT(C' . $clos . '-' . $cash_c . ',"#,##0.00"))' ) . '</row>';
		$merge[] = 'B' . $r . ':D' . $r;
		$cfchk   = $r;
		$sheets['Cash flows'] = self::sheet( $xml, array( 52, 4, 20, 20 ), $merge, 'D', $r, $h + 1, $biz, 'Statement of Cash Flows' );
		$CF = "'Cash flows'!";

		/* ---------------- Sales register ---------------- */
		list( $xml, $merge, $r ) = self::header( $biz, 'Sales Register', array( 'Paid orders (website, in-shop till and WhatsApp), all dates up to ' . self::fmtd( $book['to'] ) . '. Use the filter on Date paid for any period.' ), 'L' );
		$h    = $r;
		$xml .= '<row r="' . $h . '" ht="32" customHeight="1">' . self::t( 'A' . $h, self::S_HEAD, 'DATE PAID' ) . self::t( 'B' . $h, self::S_HEAD, 'ORDER' ) . self::t( 'C' . $h, self::S_HEAD, 'CHANNEL' ) . self::t( 'D' . $h, self::S_HEAD, 'CUSTOMER' ) . self::t( 'E' . $h, self::S_HEAD, 'PAID BY' ) . self::t( 'F' . $h, self::S_HEAD, 'REFERENCE' )
			. self::t( 'G' . $h, self::S_HEAD_R, "GOODS\nKES" ) . self::t( 'H' . $h, self::S_HEAD_R, "DELIVERY\nKES" ) . self::t( 'I' . $h, self::S_HEAD_R, "REFUNDED\nKES" ) . self::t( 'J' . $h, self::S_HEAD_R, "NET RECEIVED\nKES" ) . self::t( 'K' . $h, self::S_HEAD_R, "COST OF ITEMS\nKES" ) . self::t( 'L' . $h, self::S_HEAD_R, "GROSS PROFIT\nKES" ) . '</row>';
		$r  = $h + 1;
		$s0 = $r;
		foreach ( $book['sales'] as $s ) {
			$xml .= '<row r="' . $r . '">' . self::n( 'A' . $r, self::S_DATE, self::serial( $s['date'] ) ) . self::t( 'B' . $r, self::S_TXT, $s['no'] ) . self::t( 'C' . $r, self::S_MUTED, $s['channel'] ) . self::t( 'D' . $r, self::S_TXT, $s['name'] ) . self::t( 'E' . $r, self::S_TXT, $s['method'] ) . self::t( 'F' . $r, self::S_TXT, $s['ref'] )
				. self::n( 'G' . $r, self::S_MONEY, $s['goods'] ) . self::n( 'H' . $r, self::S_MONEY, $s['ship'] ) . self::n( 'I' . $r, self::S_MONEY, $s['refund'] ) . self::f( 'J' . $r, self::S_MONEY, 'G' . $r . '+H' . $r . '-I' . $r )
				. ( null === $s['cost'] ? self::t( 'K' . $r, self::S_ATTN, 'no buying price' ) : self::n( 'K' . $r, self::S_MONEY, $s['cost'] ) ) . self::f( 'L' . $r, self::S_MONEY, 'G' . $r . '-I' . $r . '-N(K' . $r . ')' ) . '</row>';
			$r++;
		}
		if ( $s0 === $r ) {
			$xml    .= '<row r="' . $r . '">' . self::t( 'A' . $r, self::S_MUTED, 'No paid orders yet. Mark orders as Sold in NURA Desk > Accounts when the money is received.' ) . '</row>';
			$merge[] = 'A' . $r . ':L' . $r;
			$r++;
		}
		$s1   = $r - 1;
		$xml .= '<row r="' . $r . '">' . self::t( 'A' . $r, self::S_NET, 'Totals' );
		foreach ( array( 'B', 'C', 'D', 'E', 'F' ) as $c ) {
			$xml .= self::e( $c . $r, self::S_NET );
		}
		foreach ( array( 'G', 'H', 'I', 'J', 'K', 'L' ) as $c ) {
			$xml .= self::f( $c . $r, self::S_NET_M, 'SUBTOTAL(9,' . $c . $s0 . ':' . $c . $s1 . ')' );
		}
		$xml    .= '</row>';
		$merge[] = 'A' . $r . ':F' . $r;
		$sheets['Sales register'] = self::sheet( $xml, array( 12, 11, 12, 24, 15, 16, 14, 13, 13, 15, 15, 15 ), $merge, 'L', $r, $h + 1, $biz, 'Sales Register', true, 'A' . $h . ':L' . $s1 );

		/* ---------------- Unpaid orders ---------------- */
		list( $xml, $merge, $r ) = self::header( $biz, 'Orders Awaiting Payment', array(
			'Ordered but not paid - NOT in revenue or cash. When the customer pays: website and till orders - NURA Desk > Accounts > Sold (enter method and M-Pesa code);',
			'WhatsApp orders - NURA Desk > WhatsApp orders > Mark paid. Test orders are not listed.',
		), 'F' );
		$h    = $r;
		$xml .= '<row r="' . $h . '" ht="30" customHeight="1">' . self::t( 'A' . $h, self::S_HEAD, 'ORDERED' ) . self::t( 'B' . $h, self::S_HEAD, 'ORDER' ) . self::t( 'C' . $h, self::S_HEAD, 'CHANNEL' ) . self::t( 'D' . $h, self::S_HEAD, 'CUSTOMER' ) . self::t( 'E' . $h, self::S_HEAD, 'STATUS' ) . self::t( 'F' . $h, self::S_HEAD_R, "AMOUNT\nKES" ) . '</row>';
		$r  = $h + 1;
		$u0 = $r;
		foreach ( $book['unpaid'] as $u ) {
			$xml .= '<row r="' . $r . '">' . self::n( 'A' . $r, self::S_DATE, self::serial( $u['date'] ) ) . self::t( 'B' . $r, self::S_TXT, $u['no'] ) . self::t( 'C' . $r, self::S_MUTED, $u['channel'] ) . self::t( 'D' . $r, self::S_TXT, $u['name'] ) . self::t( 'E' . $r, self::S_MUTED, $u['status'] ) . self::n( 'F' . $r, self::S_MONEY, $u['total'] ) . '</row>';
			$r++;
		}
		if ( $u0 === $r ) {
			$xml    .= '<row r="' . $r . '">' . self::t( 'A' . $r, self::S_MUTED, 'No unpaid orders.' ) . '</row>';
			$merge[] = 'A' . $r . ':F' . $r;
			$r++;
		}
		$u1   = $r - 1;
		$xml .= '<row r="' . $r . '">' . self::t( 'A' . $r, self::S_NET, 'Total awaiting payment' ) . self::e( 'B' . $r, self::S_NET ) . self::e( 'C' . $r, self::S_NET ) . self::e( 'D' . $r, self::S_NET ) . self::e( 'E' . $r, self::S_NET ) . self::f( 'F' . $r, self::S_NET_M, 'SUM(F' . $u0 . ':F' . $u1 . ')' ) . '</row>';
		$merge[]    = 'A' . $r . ':E' . $r;
		$unpaid_tot = $r;
		$sheets['Unpaid orders'] = self::sheet( $xml, array( 12, 13, 12, 30, 24, 16 ), $merge, 'F', $r, $h + 1, $biz, 'Orders Awaiting Payment', false, 'A' . $h . ':F' . $u1 );

		/* ---------------- Notes ---------------- */
		list( $xml, $merge, $r ) = self::header( $biz, 'Notes to the Accounts', array( 'Basis of preparation, accounting policies and records to check.' ), 'B' );
		$notes = array_merge( array(
			array( '1. Basis of preparation', 'Prepared from the NURA Desk records of ' . $biz['name'] . ' in Kenya Shillings (KES), presented in line with IFRS for SMEs: IAS 1 layout with expenses by nature, and IAS 7 cash flows (direct method). The books are kept by double entry: every transaction is posted to the General Journal with equal debits and credits, and every statement is calculated from the journal with live formulas.' ),
			array( '2. Revenue', 'Revenue is recognised when the customer has paid: website orders paid online or marked Sold, in-shop till sales, and WhatsApp orders marked paid. It is shown net of discounts; delivery charged to customers is shown separately; refunds reduce revenue. Ordered-but-unpaid orders are not revenue (see Unpaid orders).' ),
			array( '3. Inventory and cost of sales', 'Stock bought is recorded as inventory (an asset). When an item is sold, its buying price (saved on the order line at the moment of sale) moves from inventory to cost of sales. Items sold without a buying price have no cost recorded - see the checks below. Agree inventory to a physical stock-take (NURA Desk > Stock).' ),
			array( '4. Rent paid in advance', 'Non-refundable rent paid ahead is a prepayment (asset) and is released to rent expense in the months it covers, one month at a time on the first day of each month.' ),
			array( '5. Furniture, equipment and deposits', 'Furniture and equipment are non-current assets at cost; no depreciation has been charged yet - agree useful lives with your accountant. Refundable deposits (e.g. KPLC, water) are assets until refunded.' ),
			array( '6. Owner\'s equity', 'Money the owner puts in is owner capital; money taken out for personal use is drawings. Neither is income or expense.' ),
			array( '7. Cash and cash equivalents', 'Cash on hand, M-Pesa till, bank and Paystack (card and online M-Pesa), taken from the payment method on each order and voucher. Agree each balance monthly to the M-Pesa statement, bank statement and Paystack payouts.' ),
			array( '8. Tax', 'Income tax is not computed. Turnover tax and county levies paid are in operating expenses. Confirm the tax position with a KRA-registered tax agent.' ),
			array( '9. Using this workbook', 'Change the period dates on the Cover and every statement recalculates. Add manual journal lines at the bottom of the Journal sheet (keep debits equal to credits); the trial balance, statements and checks update automatically.' ),
		), (array) $book['notes'] );
		foreach ( $notes as $nn ) {
			$xml .= '<row r="' . $r . '" ht="66" customHeight="1">' . self::t( 'A' . $r, self::S_KPI, $nn[0] ) . self::t( 'B' . $r, self::S_NOTE, $nn[1] ) . '</row>';
			$r++;
		}
		$sheets['Notes'] = self::sheet( $xml, array( 32, 100 ), $merge, 'B', $r, 0, $biz, 'Notes to the Accounts' );

		/* ---------------- Cover ---------------- */
		list( $xml, $merge ) = self::header( $biz, 'Book of Accounts', array(
			trim( $biz['addr'] . ( $biz['pin'] ? '  |  KRA PIN ' . $biz['pin'] : '' ) ),
			$biz['phone'] . '  |  ' . $biz['email'] . '  |  ' . $biz['web'],
			'Prepared ' . ( function_exists( 'wp_date' ) ? wp_date( 'j M Y, H:i' ) : gmdate( 'j M Y, H:i' ) ) . ' by NURA Desk. Presented in Kenya Shillings (KES).',
		), 'D' );
		// Rows 9-12 hold the period inputs used by every formula in the workbook.
		$xml    .= '<row r="8" ht="22" customHeight="1">' . self::t( 'A8', self::S_SEC, 'Reporting period (change these dates and every statement recalculates)' ) . '</row>';
		$merge[] = 'A8:D8';
		foreach ( array( 9 => array( 'Period from', $book['from'] ), 10 => array( 'Period to', $book['to'] ), 11 => array( 'Comparative from', $book['pfrom'] ), 12 => array( 'Comparative to', $book['pto'] ) ) as $rr => $p ) {
			$xml    .= '<row r="' . $rr . '">' . self::t( 'A' . $rr, self::S_LINE, $p[0] ) . self::e( 'B' . $rr, self::S_LINE ) . self::n( 'C' . $rr, self::S_DATE, self::serial( $p[1] ) ) . '</row>';
			$merge[] = 'A' . $rr . ':B' . $rr;
		}
		$r       = 14;
		$xml    .= '<row r="' . $r . '" ht="26" customHeight="1">' . self::t( 'A' . $r, self::S_HEAD, 'KEY FIGURES' ) . self::e( 'B' . $r, self::S_HEAD ) . self::t( 'C' . $r, self::S_HEAD_R, strtoupper( $cl ) . "\nKES" ) . self::t( 'D' . $r, self::S_HEAD_R, strtoupper( $pl ) . "\nKES" ) . '</row>';
		$merge[] = 'A' . $r . ':B' . $r;
		$r++;
		foreach ( array(
			array( 'Revenue', $PL . 'C' . $revtot, $PL . 'D' . $revtot ),
			array( 'Gross profit', $PL . 'C' . $gp, $PL . 'D' . $gp ),
			array( 'Profit / (loss) for the period', $PL . 'C' . $net, $PL . 'D' . $net ),
			array( 'Total assets (end of period)', $SFP . 'C' . $ta, $SFP . 'D' . $ta ),
			array( 'Cash at the end of the period', $CF . 'C' . $clos, $CF . 'D' . $clos ),
			array( 'Orders awaiting payment (not in revenue)', "'Unpaid orders'!F" . $unpaid_tot, '' ),
		) as $k ) {
			$xml    .= '<row r="' . $r . '">' . self::t( 'A' . $r, self::S_KPI, $k[0] ) . self::e( 'B' . $r, self::S_KPI ) . self::f( 'C' . $r, self::S_MONEY, $k[1] ) . ( $k[2] ? self::f( 'D' . $r, self::S_MONEY, $k[2] ) : self::e( 'D' . $r, self::S_MONEY ) ) . '</row>';
			$merge[] = 'A' . $r . ':B' . $r;
			$r++;
		}
		$r++;
		$xml    .= '<row r="' . $r . '" ht="22" customHeight="1">' . self::t( 'A' . $r, self::S_SEC, 'Balance checks' ) . '</row>';
		$merge[] = 'A' . $r . ':D' . $r;
		$r++;
		foreach ( array(
			array( 'Trial balance', "'Trial balance'!B" . $tbchk ),
			array( 'Financial position', $SFP . 'B' . $sfpchk ),
			array( 'Cash flows', $CF . 'B' . $cfchk ),
		) as $c ) {
			$xml    .= '<row r="' . $r . '">' . self::t( 'A' . $r, self::S_LINE, $c[0] ) . self::f( 'B' . $r, self::S_TXT, $c[1] ) . '</row>';
			$merge[] = 'B' . $r . ':D' . $r;
			$r++;
		}
		$r++;
		$xml    .= '<row r="' . $r . '" ht="22" customHeight="1">' . self::t( 'A' . $r, self::S_SEC, 'Contents' ) . '</row>';
		$merge[] = 'A' . $r . ':D' . $r;
		$r++;
		foreach ( array(
			array( 'Profit or loss', 'Statement of Profit or Loss - current vs comparative period' ),
			array( 'Financial position', 'Statement of Financial Position (balance sheet) with balance check' ),
			array( 'Cash flows', 'Statement of Cash Flows (direct method), agreed to cash' ),
			array( 'Trial balance', 'Every account - debits equal credits' ),
			array( 'Journal', 'General journal - the source of every figure' ),
			array( 'Accounts', 'Chart of accounts with balances' ),
			array( 'Sales register', 'Paid orders with method, reference, cost and gross profit' ),
			array( 'Unpaid orders', 'Ordered but not paid - not in the books yet' ),
			array( 'Notes', 'Basis of preparation, accounting policies and records to check' ),
		) as $i => $c ) {
			$xml    .= '<row r="' . $r . '">' . self::t( 'A' . $r, self::S_LINE, ( $i + 2 ) . '. ' . $c[0] ) . self::t( 'B' . $r, self::S_MUTED, $c[1] ) . '</row>';
			$merge[] = 'B' . $r . ':D' . $r;
			$r++;
		}
		$cover = self::sheet( $xml, array( 32, 28, 20, 20 ), $merge, 'D', $r, 0, $biz, 'Book of Accounts' );

		$out = array( array( 'Cover', $cover ) );
		foreach ( array( 'Profit or loss', 'Financial position', 'Cash flows', 'Trial balance', 'Journal', 'Accounts', 'Sales register', 'Unpaid orders', 'Notes' ) as $nm ) {
			$out[] = array( $nm, $sheets[ $nm ] );
		}
		return NURAX_Xlsx_Statement::zip_send( $out, $biz, 'book-of-accounts_' . $book['from'] . '_to_' . $book['to'], $path );
	}

	/** Cash-flow lines by activity (the tags written on journal cash lines). */
	public static function cf_lines() {
		return array(
			'Operating activities' => array( 'Receipts from customers', 'Refunds paid to customers', 'Other income received', 'Paid to suppliers for stock and cost of sales', 'Operating expenses paid', 'Rent paid in advance', 'Set-up costs paid' ),
			'Investing activities' => array( 'Furniture and equipment bought', 'Refundable deposits paid' ),
			'Financing activities' => array( 'Owner capital introduced', 'Owner drawings' ),
		);
	}
}
