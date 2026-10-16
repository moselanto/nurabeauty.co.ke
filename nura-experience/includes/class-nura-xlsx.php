<?php
/**
 * NURA Desk - branded Excel (.xlsx) reports (NURA Experience 1.67.0).
 *
 * Builds a real Office Open XML workbook with ZipArchive - no libraries:
 * black + gold NURA header band with the logo, report title, period and
 * generated time, frozen gold-on-black column headers with filters, zebra
 * rows, KSh / date / number formats, coloured Yes / No, a totals row
 * (SUBTOTAL, so it follows filters), print-ready A4 landscape with page
 * numbers. Falls back to CSV when ZipArchive is missing.
 *
 * @package NURA_Experience
 */

defined( 'ABSPATH' ) || exit;

class NURAX_Xlsx {

	const S_BAND = 1, S_GOLD = 2, S_TITLE = 3, S_META = 4, S_SUB = 5, S_HEAD = 6, S_HEAD_R = 25;
	const S_TXT = 7, S_MONEY = 9, S_INT = 11, S_DATE = 13, S_DT = 15, S_YES = 17, S_NO = 19;
	const S_TOT = 21, S_TOT_M = 22, S_TOT_I = 23, S_FOOT = 24;

	public static function available() {
		return class_exists( 'ZipArchive' );
	}

	public static function titles() {
		return array(
			'sales'      => 'Sales report',
			'sale_items' => 'Sales by item',
			'contacts'   => 'Customer contacts',
			'orders'     => 'WhatsApp orders',
			'payments'   => 'Lipa Mdogo Mdogo payments',
			'appts'      => 'Appointments',
			'plans'      => 'Payment plans',
			'team'       => 'Team performance',
			'activity'   => 'Team activity log',
		);
	}

	/** Column letter(s) for a 0-based index. */
	public static function col( $i ) {
		$s = '';
		$i++;
		while ( $i > 0 ) {
			$m = ( $i - 1 ) % 26;
			$s = chr( 65 + $m ) . $s;
			$i = (int) ( ( $i - $m ) / 26 );
		}
		return $s;
	}

	public static function x( $v ) {
		$v = wp_check_invalid_utf8( (string) $v, true );
		$v = preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $v );
		return htmlspecialchars( (string) $v, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	/** Decide how each column is formatted from its header and values. */
	private static function kinds( $head, $data ) {
		$kinds = array();
		foreach ( $head as $c => $h ) {
			$h    = strtolower( (string) $h );
			$vals = array();
			foreach ( $data as $r ) {
				if ( isset( $r[ $c ] ) && '' !== (string) $r[ $c ] && null !== $r[ $c ] ) {
					$vals[] = (string) $r[ $c ];
				}
			}
			$kind = 'text';
			if ( preg_match( '/phone|sku|ref|invoice|reference|code|^id$/', $h ) ) {
				$kind = 'text';
			} elseif ( $vals && ! array_filter( $vals, function ( $v ) { return ! in_array( $v, array( 'Yes', 'No' ), true ); } ) ) {
				$kind = 'yesno';
			} elseif ( $vals && ! array_filter( $vals, function ( $v ) { return ! is_numeric( $v ); } ) ) {
				$kind = preg_match( '/total|amount|price|paid|subtotal|discount|delivery|balance|deposit|arrears|spent|fee|value/', $h ) ? 'money' : 'int';
			} elseif ( $vals && preg_match( '/date|created|received|paid on|start|due|last order|when/', $h ) && ! array_filter( $vals, function ( $v ) { return ! preg_match( '/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $v ); } ) ) {
				$kind = 'date';
				foreach ( $vals as $v ) {
					if ( strlen( $v ) > 10 && '00:00' !== substr( $v, 11, 5 ) ) {
						$kind = 'datetime';
						break;
					}
				}
			}
			$kinds[ $c ] = $kind;
		}
		return $kinds;
	}

	private static function serial( $v ) {
		$d = date_create_immutable( $v, new DateTimeZone( 'UTC' ) );
		return $d ? round( $d->getTimestamp() / 86400 + 25569, 6 ) : null;
	}

	/** Logo as PNG bytes (resized to 600px) or array() when none. */
	private static function logo() {
		$path = '';
		$s    = class_exists( 'NURAX_Desk' ) ? NURAX_Desk::settings() : array();
		if ( ! empty( $s['invoice_logo'] ) ) {
			$id = attachment_url_to_postid( $s['invoice_logo'] );
			if ( $id ) {
				$path = (string) get_attached_file( $id );
			}
		}
		if ( ! $path ) {
			$id = (int) get_theme_mod( 'custom_logo' );
			if ( $id ) {
				$path = (string) get_attached_file( $id );
			}
		}
		if ( ! $path || ! file_exists( $path ) ) {
			return array();
		}
		$bytes = '';
		$ext   = 'png';
		if ( function_exists( 'wp_get_image_editor' ) ) {
			$ed = wp_get_image_editor( $path );
			if ( ! is_wp_error( $ed ) ) {
				$sz = $ed->get_size();
				if ( ! empty( $sz['width'] ) && $sz['width'] > 600 ) {
					$ed->resize( 600, null, false );
				}
				$tmp = wp_tempnam( 'nura-logo' ) . '.png';
				$res = $ed->save( $tmp, 'image/png' );
				if ( ! is_wp_error( $res ) && ! empty( $res['path'] ) && file_exists( $res['path'] ) ) {
					$bytes = (string) file_get_contents( $res['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
					wp_delete_file( $res['path'] );
				}
				if ( file_exists( $tmp ) ) {
					wp_delete_file( $tmp );
				}
			}
		}
		if ( '' === $bytes ) {
			$mime = wp_check_filetype( $path );
			if ( ! in_array( $mime['type'], array( 'image/png', 'image/jpeg' ), true ) ) {
				return array();
			}
			$bytes = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$ext   = 'image/png' === $mime['type'] ? 'png' : 'jpeg';
		}
		$info = function_exists( 'getimagesizefromstring' ) ? @getimagesizefromstring( $bytes ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! $info || empty( $info[0] ) || empty( $info[1] ) ) {
			return array();
		}
		$h = 60;
		$w = (int) round( $info[0] * $h / $info[1] );
		if ( $w > 280 ) {
			$w = 280;
			$h = (int) round( $info[1] * $w / $info[0] );
		}
		return array( 'bytes' => $bytes, 'ext' => $ext, 'w' => $w, 'h' => $h );
	}

	private static function styles() {
		$font = function ( $sz, $rgb, $extra = '', $name = 'Calibri' ) {
			return '<font>' . $extra . '<sz val="' . $sz . '"/><color rgb="FF' . $rgb . '"/><name val="' . $name . '"/></font>';
		};
		$fonts = array(
			$font( 10, '1B1B1B' ),
			$font( 18, '0E0E0E', '<b/>', 'Georgia' ),
			$font( 9, '8A8174' ),
			$font( 11, 'E4C983', '<b/>' ),
			$font( 10, 'E4C983', '<b/>' ),
			$font( 10, '1F7A45', '<b/>' ),
			$font( 10, 'B3261E' ),
			$font( 10, '6F675D' ),
			$font( 10, '0E0E0E', '<b/>' ),
			$font( 9, '8A8174', '<i/>' ),
		);
		$fill  = function ( $rgb ) {
			return '<fill><patternFill patternType="solid"><fgColor rgb="FF' . $rgb . '"/><bgColor indexed="64"/></patternFill></fill>';
		};
		$fills = '<fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>' . $fill( '0E0E0E' ) . $fill( 'C9A24B' ) . $fill( 'FAF6EF' ) . $fill( 'FFFFFF' ) . $fill( 'F3E6C4' );
		$b     = function ( $st, $rgb ) {
			return $st ? '<' . '%s style="' . $st . '"><color rgb="FF' . $rgb . '"/></%s>' : '<%s/>';
		};
		$edge  = function ( $tag, $st, $rgb ) {
			return $st ? '<' . $tag . ' style="' . $st . '"><color rgb="FF' . $rgb . '"/></' . $tag . '>' : '<' . $tag . '/>';
		};
		unset( $b );
		$borders  = '<border><left/><right/><top/><bottom/><diagonal/></border>';
		$borders .= '<border>' . $edge( 'left', 'thin', 'ECE4D6' ) . $edge( 'right', 'thin', 'ECE4D6' ) . $edge( 'top', 'thin', 'ECE4D6' ) . $edge( 'bottom', 'thin', 'ECE4D6' ) . '<diagonal/></border>';
		$borders .= '<border>' . $edge( 'left', 'thin', '2A2622' ) . $edge( 'right', 'thin', '2A2622' ) . '<top/>' . $edge( 'bottom', 'medium', 'C9A24B' ) . '<diagonal/></border>';
		$borders .= '<border><left/><right/>' . $edge( 'top', 'medium', '0E0E0E' ) . $edge( 'bottom', 'medium', 'C9A24B' ) . '<diagonal/></border>';

		$xf = function ( $font, $fill, $border, $num = 0, $h = '', $wrap = false, $indent = 0 ) {
			$a = '<alignment vertical="center"' . ( $h ? ' horizontal="' . $h . '"' : '' ) . ( $wrap ? ' wrapText="1"' : '' ) . ( $indent ? ' indent="' . $indent . '"' : '' ) . '/>';
			return '<xf numFmtId="' . $num . '" fontId="' . $font . '" fillId="' . $fill . '" borderId="' . $border . '" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"' . ( $num ? ' applyNumberFormat="1"' : '' ) . '>' . $a . '</xf>';
		};
		$x   = array();
		$x[] = '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>';      // 0
		$x[] = $xf( 3, 2, 0, 0, 'right', false, 1 );                                  // 1 band
		$x[] = $xf( 0, 3, 0 );                                                         // 2 gold strip
		$x[] = $xf( 1, 0, 0, 0, 'left' );                                              // 3 title
		$x[] = $xf( 2, 0, 0, 0, 'right' );                                             // 4 meta
		$x[] = $xf( 7, 0, 0, 0, 'left' );                                              // 5 subtitle
		$x[] = $xf( 4, 2, 2, 0, 'left', true, 1 );                                     // 6 header
		$x[] = $xf( 0, 5, 1, 0, 'left', false, 1 );                                    // 7 text odd
		$x[] = $xf( 0, 4, 1, 0, 'left', false, 1 );                                    // 8 text even
		$x[] = $xf( 0, 5, 1, 164, 'right', false, 1 );                                 // 9 money
		$x[] = $xf( 0, 4, 1, 164, 'right', false, 1 );                                 // 10
		$x[] = $xf( 0, 5, 1, 3, 'right', false, 1 );                                   // 11 int
		$x[] = $xf( 0, 4, 1, 3, 'right', false, 1 );                                   // 12
		$x[] = $xf( 0, 5, 1, 165, 'left', false, 1 );                                  // 13 date
		$x[] = $xf( 0, 4, 1, 165, 'left', false, 1 );                                  // 14
		$x[] = $xf( 0, 5, 1, 166, 'left', false, 1 );                                  // 15 datetime
		$x[] = $xf( 0, 4, 1, 166, 'left', false, 1 );                                  // 16
		$x[] = $xf( 5, 5, 1, 0, 'center' );                                            // 17 yes
		$x[] = $xf( 5, 4, 1, 0, 'center' );                                            // 18
		$x[] = $xf( 6, 5, 1, 0, 'center' );                                            // 19 no
		$x[] = $xf( 6, 4, 1, 0, 'center' );                                            // 20
		$x[] = $xf( 8, 6, 3, 0, 'left', false, 1 );                                    // 21 total label
		$x[] = $xf( 8, 6, 3, 164, 'right', false, 1 );                                 // 22 total money
		$x[] = $xf( 8, 6, 3, 3, 'right', false, 1 );                                   // 23 total int
		$x[] = $xf( 9, 0, 0, 0, 'left' );                                              // 24 footer
		$x[] = $xf( 4, 2, 2, 0, 'right', true, 1 );                                    // 25 header right

		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<numFmts count="3"><numFmt numFmtId="164" formatCode="&quot;KSh &quot;#,##0;[Red]&quot;- KSh &quot;#,##0;&quot;KSh &quot;0"/><numFmt numFmtId="165" formatCode="d mmm yyyy"/><numFmt numFmtId="166" formatCode="d mmm yyyy, hh:mm"/></numFmts>'
			. '<fonts count="' . count( $fonts ) . '">' . implode( '', $fonts ) . '</fonts>'
			. '<fills count="7">' . $fills . '</fills>'
			. '<borders count="4">' . $borders . '</borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="' . count( $x ) . '">' . implode( '', $x ) . '</cellXfs>'
			. '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
			. '</styleSheet>';
	}

	private static function cs( $ref, $s, $text ) {
		return '<c r="' . $ref . '" s="' . $s . '" t="inlineStr"><is><t xml:space="preserve">' . self::x( $text ) . '</t></is></c>';
	}

	private static function cn( $ref, $s, $n ) {
		return '<c r="' . $ref . '" s="' . $s . '"><v>' . ( 0 + $n ) . '</v></c>';
	}

	private static function ce( $ref, $s ) {
		return '<c r="' . $ref . '" s="' . $s . '"/>';
	}

	/**
	 * Build the workbook and stream it to the browser.
	 *
	 * @param array  $rows First row = headers.
	 * @param string $type Report key.
	 * @param array  $meta Optional 'period' text.
	 */
	public static function send( $rows, $type, $meta = array() ) {
		$titles = self::titles();
		$title  = isset( $titles[ $type ] ) ? $titles[ $type ] : 'NURA report';
		$head   = array_values( (array) array_shift( $rows ) );
		$data   = array_map( 'array_values', $rows );
		$n      = max( 1, count( $head ) );
		$last   = self::col( $n - 1 );
		$kinds  = self::kinds( $head, $data );
		$biz    = class_exists( 'NURAX_Desk_POS' ) ? NURAX_Desk_POS::biz() : array( 'name' => 'NURA Beauty', 'web' => '', 'phone' => '', 'email' => '' );
		$logo   = self::logo();
		$sheet  = substr( preg_replace( '/[\[\]\:\*\?\/\\\\]/', '', $title ), 0, 31 );
		$count  = count( $data );

		/* Column widths from content. */
		$cols = '';
		foreach ( $head as $c => $h ) {
			$w = max( 10, mb_strlen( (string) $h ) + 4 );
			foreach ( array_slice( $data, 0, 400 ) as $r ) {
				$v = isset( $r[ $c ] ) ? (string) $r[ $c ] : '';
				$l = in_array( $kinds[ $c ], array( 'money', 'int' ), true ) ? strlen( number_format( (float) $v ) ) + 6 : ( 'date' === $kinds[ $c ] ? 13 : ( 'datetime' === $kinds[ $c ] ? 19 : mb_strlen( $v ) + 3 ) );
				$w = max( $w, $l );
			}
			if ( 0 === $c && $logo ) {
				$w = max( $w, 14 );
			}
			$cols .= '<col min="' . ( $c + 1 ) . '" max="' . ( $c + 1 ) . '" width="' . min( 46, $w ) . '" customWidth="1"/>';
		}

		$merge = array();
		$xml   = '';
		$band  = function ( $r, $s, $text = null ) use ( $n, $last ) {
			$o = '';
			for ( $c = 0; $c < $n; $c++ ) {
				$o .= ( 0 === $c && null !== $text ) ? self::cs( 'A' . $r, $s, $text ) : self::ce( self::col( $c ) . $r, $s );
			}
			return $o;
		};

		/* 1: black band with logo + report label. 2: gold strip. */
		$xml    .= '<row r="1" ht="62" customHeight="1">' . $band( 1, self::S_BAND, strtoupper( $biz['name'] . '   |   ' . $title ) ) . '</row>';
		$merge[] = 'A1:' . $last . '1';
		$xml    .= '<row r="2" ht="4" customHeight="1">' . $band( 2, self::S_GOLD ) . '</row>';
		$merge[] = 'A2:' . $last . '2';

		/* 3: title + generated. 4: period / records. */
		$gen = 'Generated ' . wp_date( 'j M Y, H:i' ) . ( $biz['web'] ? '  |  ' . $biz['web'] : '' );
		$mid = $n >= 4 ? (int) ceil( $n / 2 ) : $n;
		$r3  = self::cs( 'A3', self::S_TITLE, $title );
		for ( $c = 1; $c < $mid; $c++ ) {
			$r3 .= self::ce( self::col( $c ) . '3', self::S_TITLE );
		}
		if ( $mid < $n ) {
			$r3 .= self::cs( self::col( $mid ) . '3', self::S_META, $gen );
			for ( $c = $mid + 1; $c < $n; $c++ ) {
				$r3 .= self::ce( self::col( $c ) . '3', self::S_META );
			}
			$merge[] = self::col( $mid ) . '3:' . $last . '3';
		}
		if ( $mid > 1 ) {
			$merge[] = 'A3:' . self::col( $mid - 1 ) . '3';
		}
		$xml .= '<row r="3" ht="34" customHeight="1">' . $r3 . '</row>';

		$sub = ( ! empty( $meta['period'] ) ? $meta['period'] : 'All records' ) . '  |  ' . number_format( $count ) . ( 1 === $count ? ' record' : ' records' ) . ( $mid >= $n ? '  |  ' . $gen : '' );
		$xml    .= '<row r="4" ht="20" customHeight="1">' . $band( 4, self::S_SUB, $sub ) . '</row>';
		$merge[] = 'A4:' . $last . '4';
		$xml    .= '<row r="5" ht="10" customHeight="1"></row>';

		/* 6: headers. */
		$r6 = '';
		foreach ( $head as $c => $h ) {
			$r6 .= self::cs( self::col( $c ) . '6', in_array( $kinds[ $c ], array( 'money', 'int' ), true ) ? self::S_HEAD_R : self::S_HEAD, strtoupper( (string) $h ) );
		}
		$xml .= '<row r="6" ht="30" customHeight="1">' . $r6 . '</row>';

		/* Data. */
		$r = 6;
		foreach ( $data as $i => $row ) {
			$r++;
			$z  = $i % 2;
			$cx = '';
			for ( $c = 0; $c < $n; $c++ ) {
				$v   = isset( $row[ $c ] ) ? $row[ $c ] : '';
				$ref = self::col( $c ) . $r;
				$k   = $kinds[ $c ];
				if ( '' === (string) $v || null === $v ) {
					$cx .= self::ce( $ref, ( 'money' === $k ? self::S_MONEY : ( 'int' === $k ? self::S_INT : self::S_TXT ) ) + $z );
				} elseif ( 'money' === $k ) {
					$cx .= self::cn( $ref, self::S_MONEY + $z, (float) $v );
				} elseif ( 'int' === $k ) {
					$cx .= self::cn( $ref, self::S_INT + $z, (float) $v );
				} elseif ( ( 'date' === $k || 'datetime' === $k ) && null !== self::serial( $v ) ) {
					$cx .= self::cn( $ref, ( 'date' === $k ? self::S_DATE : self::S_DT ) + $z, self::serial( $v ) );
				} elseif ( 'yesno' === $k ) {
					$cx .= self::cs( $ref, ( 'Yes' === $v ? self::S_YES : self::S_NO ) + $z, $v );
				} else {
					$cx .= self::cs( $ref, self::S_TXT + $z, ucfirst( (string) $v ) === (string) $v ? $v : ( preg_match( '/^[a-z][a-z\-]+$/', (string) $v ) ? ucfirst( str_replace( '-', ' ', $v ) ) : $v ) );
				}
			}
			$xml .= '<row r="' . $r . '">' . $cx . '</row>';
		}
		$first = 7;
		$lastr = max( 6, $r );

		/* Totals. */
		$has = in_array( 'money', $kinds, true );
		if ( $has && $count ) {
			$r++;
			$tx = '';
			for ( $c = 0; $c < $n; $c++ ) {
				$ref = self::col( $c ) . $r;
				$k   = $kinds[ $c ];
				if ( 0 === $c ) {
					$tx .= self::cs( $ref, self::S_TOT, 'TOTAL' );
				} elseif ( 'money' === $k || ( 'int' === $k && preg_match( '/qty|quantity|orders/i', (string) $head[ $c ] ) ) ) {
					$tx .= '<c r="' . $ref . '" s="' . ( 'money' === $k ? self::S_TOT_M : self::S_TOT_I ) . '"><f>SUBTOTAL(109,' . self::col( $c ) . $first . ':' . self::col( $c ) . $lastr . ')</f></c>';
				} else {
					$tx .= self::ce( $ref, self::S_TOT );
				}
			}
			$xml .= '<row r="' . $r . '" ht="26" customHeight="1">' . $tx . '</row>';
		}
		if ( ! $count ) {
			$r++;
			$xml    .= '<row r="' . $r . '" ht="26" customHeight="1">' . $band( $r, self::S_SUB, 'No records for this period.' ) . '</row>';
			$merge[] = 'A' . $r . ':' . $last . $r;
		}

		/* Footer note. */
		$r      += 2;
		$foot    = $biz['name'] . '  |  ' . trim( $biz['phone'] . '  |  ' . $biz['email'], ' |' ) . '  |  Confidential - prepared by NURA Desk. Amounts in Kenya Shillings (KES).';
		$xml    .= '<row r="' . $r . '" ht="18" customHeight="1">' . $band( $r, self::S_FOOT, $foot ) . '</row>';
		$merge[] = 'A' . $r . ':' . $last . $r;

		$mc = '<mergeCells count="' . count( $merge ) . '">';
		foreach ( $merge as $m ) {
			$mc .= '<mergeCell ref="' . $m . '"/>';
		}
		$mc .= '</mergeCells>';

		$ws  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$ws .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
		$ws .= '<sheetPr><tabColor rgb="FFC9A24B"/><pageSetUpPr fitToPage="1"/></sheetPr>';
		$ws .= '<dimension ref="A1:' . $last . $r . '"/>';
		$ws .= '<sheetViews><sheetView showGridLines="0" zoomScale="110" zoomScaleNormal="110" workbookViewId="0"><pane ySplit="6" topLeftCell="A7" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A7" sqref="A7"/></sheetView></sheetViews>';
		$ws .= '<sheetFormatPr defaultRowHeight="21" customHeight="1"/>';
		$ws .= '<cols>' . $cols . '</cols>';
		$ws .= '<sheetData>' . $xml . '</sheetData>';
		$ws .= '<autoFilter ref="A6:' . $last . $lastr . '"/>';
		$ws .= $mc;
		$ws .= '<printOptions horizontalCentered="1"/>';
		$ws .= '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.6" header="0.3" footer="0.3"/>';
		$ws .= '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>';
		$ws .= '<headerFooter><oddFooter>' . self::x( '&L&8' . $biz['name'] . ' - ' . $title . '&C&8Page &P of &N&R&8' . wp_date( 'j M Y' ) ) . '</oddFooter></headerFooter>';
		if ( $logo ) {
			$ws .= '<drawing r:id="rId1"/>';
		}
		$ws .= '</worksheet>';

		$q    = "'" . str_replace( "'", "''", $sheet ) . "'";
		$wb   = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<bookViews><workbookView activeTab="0"/></bookViews>'
			. '<sheets><sheet name="' . self::x( $sheet ) . '" sheetId="1" r:id="rId1"/></sheets>'
			. '<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">' . self::x( $q ) . '!$A$6:$' . $last . '$' . $lastr . '</definedName>'
			. '<definedName name="_xlnm.Print_Titles" localSheetId="0">' . self::x( $q ) . '!$6:$6</definedName></definedNames>'
			. '</workbook>';
		$ext  = $logo ? $logo['ext'] : 'png';
		$ct   = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Default Extension="png" ContentType="image/png"/>'
			. '<Default Extension="jpeg" ContentType="image/jpeg"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
			. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
			. ( $logo ? '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>' : '' )
			. '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
			. '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
			. '</Types>';
		$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
			. '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
			. '</Relationships>';
		$wbr  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
			. '</Relationships>';
		$now  = gmdate( 'Y-m-d\TH:i:s\Z' );
		$core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
			. '<dc:title>' . self::x( $biz['name'] . ' - ' . $title ) . '</dc:title><dc:creator>NURA Desk</dc:creator><cp:lastModifiedBy>NURA Desk</cp:lastModifiedBy>'
			. '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
			. '</cp:coreProperties>';
		$app  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>NURA Desk</Application><Company>' . self::x( $biz['name'] ) . '</Company></Properties>';

		$tmp = wp_tempnam( 'nura-xlsx' );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return false;
		}
		$zip->addFromString( '[Content_Types].xml', $ct );
		$zip->addFromString( '_rels/.rels', $rels );
		$zip->addFromString( 'docProps/core.xml', $core );
		$zip->addFromString( 'docProps/app.xml', $app );
		$zip->addFromString( 'xl/workbook.xml', $wb );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', $wbr );
		$zip->addFromString( 'xl/styles.xml', self::styles() );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', $ws );
		if ( $logo ) {
			$cx = $logo['w'] * 9525;
			$cy = $logo['h'] * 9525;
			$zip->addFromString( 'xl/worksheets/_rels/sheet1.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/></Relationships>' );
			$zip->addFromString( 'xl/drawings/_rels/drawing1.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/image1.' . $ext . '"/></Relationships>' );
			$zip->addFromString(
				'xl/drawings/drawing1.xml',
				'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
				. '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
				. '<xdr:oneCellAnchor><xdr:from><xdr:col>0</xdr:col><xdr:colOff>123825</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>' . ( max( 0, (int) ( ( 82 - $logo['h'] ) / 2 ) ) * 9525 ) . '</xdr:rowOff></xdr:from>'
				. '<xdr:ext cx="' . $cx . '" cy="' . $cy . '"/>'
				. '<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="2" name="Logo" descr="' . self::x( $biz['name'] ) . '"/><xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr>'
				. '<xdr:blipFill><a:blip r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
				. '<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic>'
				. '<xdr:clientData/></xdr:oneCellAnchor></xdr:wsDr>'
			);
			$zip->addFromString( 'xl/media/image1.' . $ext, $logo['bytes'] );
		}
		$zip->close();

		$file = sanitize_file_name( strtolower( str_replace( ' ', '-', $biz['name'] . '-' . $title ) ) . '-' . current_time( 'Y-m-d' ) . '.xlsx' );
		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="' . $file . '"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		wp_delete_file( $tmp );
		return true;
	}
}
