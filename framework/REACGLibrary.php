<?php

class REACGLibrary {

  public static $pro_icon = '<svg width="30px" height="30px" viewBox="0 0 120 120" id="Layer_1" version="1.1" xmlns="http://www.w3.org/2000/svg"><g><polygon class="st0" points="75.7,107.4 60,97.5 44.3,107.4 44.3,41.1 75.7,41.1  "></polygon><circle class="st1" cx="60" cy="44.8" r="32.2"></circle><circle class="st2" cx="60" cy="44.8" r="25.3"></circle><path class="st3" d="M61.2,29.7l4.2,8.4c0.2,0.4,0.6,0.7,1,0.8l9.3,1.4c1.1,0.2,1.6,1.5,0.8,2.3l-6.7,6.6c-0.3,0.3-0.5,0.8-0.4,1.2   l1.6,9.3c0.2,1.1-1,2-2,1.4l-8.3-4.4c-0.4-0.2-0.9-0.2-1.3,0L51,61.1c-1,0.5-2.2-0.3-2-1.4l1.6-9.3c0.1-0.4-0.1-0.9-0.4-1.2   l-6.7-6.6c-0.8-0.8-0.4-2.2,0.8-2.3l9.3-1.4c0.4-0.1,0.8-0.3,1-0.8l4.2-8.4C59.3,28.7,60.7,28.7,61.2,29.7z"></path></g></svg>';
  /**
   * Get ABSPATH from WP_CONTENT_DIR.
   *
   * @return string
   */
  public static function get_abspath() {
    $dirpath = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : ABSPATH;
    $folder_name = defined('WP_CONTENT_FOLDERNAME') ? WP_CONTENT_FOLDERNAME : "wp-content";
    $array = explode($folder_name, $dirpath);
    if ( isset($array[0]) && $array[0] != "" ) {
      return $array[0];
    }

    return ABSPATH;
  }

  /**
   * Get Google fonts.
   *
   * @param WP_REST_Request $request
   *
   * @return null
   */
  public static function get_fonts($json = true) {
    $initial = array('Inherit' => 'Inherit');
    $google_fonts = array('ABeeZee'=>'ABeeZee','Abel'=>'Abel','Abhaya Libre'=>'Abhaya Libre','Abril Fatface'=>'Abril Fatface','Aclonica'=>'Aclonica','Acme'=>'Acme','Actor'=>'Actor','Adamina'=>'Adamina','Advent Pro'=>'Advent Pro','Aguafina Script'=>'Aguafina Script','Akronim'=>'Akronim','Aladin'=>'Aladin','Alata'=>'Alata','Alatsi'=>'Alatsi','Aldrich'=>'Aldrich','Alef'=>'Alef','Alegreya'=>'Alegreya','Alegreya SC'=>'Alegreya SC','Alegreya Sans'=>'Alegreya Sans','Alegreya Sans SC'=>'Alegreya Sans SC','Aleo'=>'Aleo','Alex Brush'=>'Alex Brush','Alfa Slab One'=>'Alfa Slab One','Alice'=>'Alice','Alike'=>'Alike','Alike Angular'=>'Alike Angular','Allan'=>'Allan','Allerta'=>'Allerta','Allerta Stencil'=>'Allerta Stencil','Allura'=>'Allura','Almarai'=>'Almarai','Almendra'=>'Almendra','Almendra Display'=>'Almendra Display','Almendra SC'=>'Almendra SC','Amarante'=>'Amarante','Amaranth'=>'Amaranth','Amatic SC'=>'Amatic SC','Amethysta'=>'Amethysta','Amiko'=>'Amiko','Amiri'=>'Amiri','Amita'=>'Amita','Anaheim'=>'Anaheim','Andada'=>'Andada','Andika'=>'Andika','Angkor'=>'Angkor','Annie Use Your Telescope'=>'Annie Use Your Telescope','Anonymous Pro'=>'Anonymous Pro','Antic'=>'Antic','Antic Didone'=>'Antic Didone','Antic Slab'=>'Antic Slab','Anton'=>'Anton','Arapey'=>'Arapey','Arbutus'=>'Arbutus','Arbutus Slab'=>'Arbutus Slab','Architects Daughter'=>'Architects Daughter','Archivo'=>'Archivo','Archivo Black'=>'Archivo Black','Archivo Narrow'=>'Archivo Narrow','Aref Ruqaa'=>'Aref Ruqaa','Arima Madurai'=>'Arima Madurai','Arimo'=>'Arimo','Arizonia'=>'Arizonia','Armata'=>'Armata','Arsenal'=>'Arsenal','Artifika'=>'Artifika','Arvo'=>'Arvo','Arya'=>'Arya','Asap'=>'Asap','Asap Condensed'=>'Asap Condensed','Asar'=>'Asar','Asset'=>'Asset','Assistant'=>'Assistant','Astloch'=>'Astloch','Asul'=>'Asul','Athiti'=>'Athiti','Atma'=>'Atma','Atomic Age'=>'Atomic Age','Aubrey'=>'Aubrey','Audiowide'=>'Audiowide','Autour One'=>'Autour One','Average'=>'Average','Average Sans'=>'Average Sans','Averia Gruesa Libre'=>'Averia Gruesa Libre','Averia Libre'=>'Averia Libre','Averia Sans Libre'=>'Averia Sans Libre','Averia Serif Libre'=>'Averia Serif Libre','B612'=>'B612','B612 Mono'=>'B612 Mono','Bad Script'=>'Bad Script','Bahiana'=>'Bahiana','Bahianita'=>'Bahianita','Bai Jamjuree'=>'Bai Jamjuree','Baloo'=>'Baloo','Baloo Bhai'=>'Baloo Bhai','Baloo Bhaijaan'=>'Baloo Bhaijaan','Baloo Bhaina'=>'Baloo Bhaina','Baloo Chettan'=>'Baloo Chettan','Baloo Da'=>'Baloo Da','Baloo Paaji'=>'Baloo Paaji','Baloo Tamma'=>'Baloo Tamma','Baloo Tammudu'=>'Baloo Tammudu','Baloo Thambi'=>'Baloo Thambi','Balthazar'=>'Balthazar','Bangers'=>'Bangers','Barlow'=>'Barlow','Barlow Condensed'=>'Barlow Condensed','Barlow Semi Condensed'=>'Barlow Semi Condensed','Barriecito'=>'Barriecito','Barrio'=>'Barrio','Basic'=>'Basic','Baskervville'=>'Baskervville','Battambang'=>'Battambang','Baumans'=>'Baumans','Bayon'=>'Bayon','Be Vietnam'=>'Be Vietnam','Bebas Neue'=>'Bebas Neue','Belgrano'=>'Belgrano','Bellefair'=>'Bellefair','Belleza'=>'Belleza','BenchNine'=>'BenchNine','Bentham'=>'Bentham','Berkshire Swash'=>'Berkshire Swash','Beth Ellen'=>'Beth Ellen','Bevan'=>'Bevan','Big Shoulders Display'=>'Big Shoulders Display','Big Shoulders Text'=>'Big Shoulders Text','Bigelow Rules'=>'Bigelow Rules','Bigshot One'=>'Bigshot One','Bilbo'=>'Bilbo','Bilbo Swash Caps'=>'Bilbo Swash Caps','BioRhyme'=>'BioRhyme','BioRhyme Expanded'=>'BioRhyme Expanded','Biryani'=>'Biryani','Bitter'=>'Bitter','Black And White Picture'=>'Black And White Picture','Black Han Sans'=>'Black Han Sans','Black Ops One'=>'Black Ops One','Blinker'=>'Blinker','Bokor'=>'Bokor','Bonbon'=>'Bonbon','Boogaloo'=>'Boogaloo','Bowlby One'=>'Bowlby One','Bowlby One SC'=>'Bowlby One SC','Brawler'=>'Brawler','Bree Serif'=>'Bree Serif','Bubblegum Sans'=>'Bubblegum Sans','Bubbler One'=>'Bubbler One','Buda'=>'Buda','Buenard'=>'Buenard','Bungee'=>'Bungee','Bungee Hairline'=>'Bungee Hairline','Bungee Inline'=>'Bungee Inline','Bungee Outline'=>'Bungee Outline','Bungee Shade'=>'Bungee Shade','Butcherman'=>'Butcherman','Butterfly Kids'=>'Butterfly Kids','Cabin'=>'Cabin','Cabin Condensed'=>'Cabin Condensed','Cabin Sketch'=>'Cabin Sketch','Caesar Dressing'=>'Caesar Dressing','Cagliostro'=>'Cagliostro','Cairo'=>'Cairo','Calistoga'=>'Calistoga','Calligraffitti'=>'Calligraffitti','Cambay'=>'Cambay','Cambo'=>'Cambo','Candal'=>'Candal','Cantarell'=>'Cantarell','Cantata One'=>'Cantata One','Cantora One'=>'Cantora One','Capriola'=>'Capriola','Cardo'=>'Cardo','Carme'=>'Carme','Carrois Gothic'=>'Carrois Gothic','Carrois Gothic SC'=>'Carrois Gothic SC','Carter One'=>'Carter One','Catamaran'=>'Catamaran','Caudex'=>'Caudex','Caveat'=>'Caveat','Caveat Brush'=>'Caveat Brush','Cedarville Cursive'=>'Cedarville Cursive','Ceviche One'=>'Ceviche One','Chakra Petch'=>'Chakra Petch','Changa'=>'Changa','Changa One'=>'Changa One','Chango'=>'Chango','Charm'=>'Charm','Charmonman'=>'Charmonman','Chathura'=>'Chathura','Chau Philomene One'=>'Chau Philomene One','Chela One'=>'Chela One','Chelsea Market'=>'Chelsea Market','Chenla'=>'Chenla','Cherry Cream Soda'=>'Cherry Cream Soda','Cherry Swash'=>'Cherry Swash','Chewy'=>'Chewy','Chicle'=>'Chicle','Chilanka'=>'Chilanka','Chivo'=>'Chivo','Chonburi'=>'Chonburi','Cinzel'=>'Cinzel','Cinzel Decorative'=>'Cinzel Decorative','Clicker Script'=>'Clicker Script','Coda'=>'Coda','Coda Caption'=>'Coda Caption','Codystar'=>'Codystar','Coiny'=>'Coiny','Combo'=>'Combo','Comfortaa'=>'Comfortaa','Coming Soon'=>'Coming Soon','Concert One'=>'Concert One','Condiment'=>'Condiment','Content'=>'Content','Contrail One'=>'Contrail One','Convergence'=>'Convergence','Cookie'=>'Cookie','Copse'=>'Copse','Corben'=>'Corben','Cormorant'=>'Cormorant','Cormorant Garamond'=>'Cormorant Garamond','Cormorant Infant'=>'Cormorant Infant','Cormorant SC'=>'Cormorant SC','Cormorant Unicase'=>'Cormorant Unicase','Cormorant Upright'=>'Cormorant Upright','Courgette'=>'Courgette','Courier Prime'=>'Courier Prime','Cousine'=>'Cousine','Coustard'=>'Coustard','Covered By Your Grace'=>'Covered By Your Grace','Crafty Girls'=>'Crafty Girls','Creepster'=>'Creepster','Crete Round'=>'Crete Round','Crimson Pro'=>'Crimson Pro','Crimson Text'=>'Crimson Text','Croissant One'=>'Croissant One','Crushed'=>'Crushed','Cuprum'=>'Cuprum','Cute Font'=>'Cute Font','Cutive'=>'Cutive','Cutive Mono'=>'Cutive Mono','DM Sans'=>'DM Sans','DM Serif Display'=>'DM Serif Display','DM Serif Text'=>'DM Serif Text','Damion'=>'Damion','Dancing Script'=>'Dancing Script','Dangrek'=>'Dangrek','Darker Grotesque'=>'Darker Grotesque','David Libre'=>'David Libre','Dawning of a New Day'=>'Dawning of a New Day','Days One'=>'Days One','Dekko'=>'Dekko','Delius'=>'Delius','Delius Swash Caps'=>'Delius Swash Caps','Delius Unicase'=>'Delius Unicase','Della Respira'=>'Della Respira','Denk One'=>'Denk One','Devonshire'=>'Devonshire','Dhurjati'=>'Dhurjati','Didact Gothic'=>'Didact Gothic','Diplomata'=>'Diplomata','Diplomata SC'=>'Diplomata SC','Do Hyeon'=>'Do Hyeon','Dokdo'=>'Dokdo','Domine'=>'Domine','Donegal One'=>'Donegal One','Doppio One'=>'Doppio One','Dorsa'=>'Dorsa','Dosis'=>'Dosis','Dr Sugiyama'=>'Dr Sugiyama','Duru Sans'=>'Duru Sans','Dynalight'=>'Dynalight','EB Garamond'=>'EB Garamond','Eagle Lake'=>'Eagle Lake','East Sea Dokdo'=>'East Sea Dokdo','Eater'=>'Eater','Economica'=>'Economica','Eczar'=>'Eczar','El Messiri'=>'El Messiri','Electrolize'=>'Electrolize','Elsie'=>'Elsie','Elsie Swash Caps'=>'Elsie Swash Caps','Emblema One'=>'Emblema One','Emilys Candy'=>'Emilys Candy','Encode Sans'=>'Encode Sans','Encode Sans Condensed'=>'Encode Sans Condensed','Encode Sans Expanded'=>'Encode Sans Expanded','Encode Sans Semi Condensed'=>'Encode Sans Semi Condensed','Encode Sans Semi Expanded'=>'Encode Sans Semi Expanded','Engagement'=>'Engagement','Englebert'=>'Englebert','Enriqueta'=>'Enriqueta','Erica One'=>'Erica One','Esteban'=>'Esteban','Euphoria Script'=>'Euphoria Script','Ewert'=>'Ewert','Exo'=>'Exo','Exo 2'=>'Exo 2','Expletus Sans'=>'Expletus Sans','Fahkwang'=>'Fahkwang','Fanwood Text'=>'Fanwood Text','Farro'=>'Farro','Farsan'=>'Farsan','Fascinate'=>'Fascinate','Fascinate Inline'=>'Fascinate Inline','Faster One'=>'Faster One','Fasthand'=>'Fasthand','Fauna One'=>'Fauna One','Faustina'=>'Faustina','Federant'=>'Federant','Federo'=>'Federo','Felipa'=>'Felipa','Fenix'=>'Fenix','Finger Paint'=>'Finger Paint','Fira Code'=>'Fira Code','Fira Mono'=>'Fira Mono','Fira Sans'=>'Fira Sans','Fira Sans Condensed'=>'Fira Sans Condensed','Fira Sans Extra Condensed'=>'Fira Sans Extra Condensed','Fjalla One'=>'Fjalla One','Fjord One'=>'Fjord One','Flamenco'=>'Flamenco','Flavors'=>'Flavors','Fondamento'=>'Fondamento','Fontdiner Swanky'=>'Fontdiner Swanky','Forum'=>'Forum','Francois One'=>'Francois One','Frank Ruhl Libre'=>'Frank Ruhl Libre','Freckle Face'=>'Freckle Face','Fredericka the Great'=>'Fredericka the Great','Fredoka One'=>'Fredoka One','Freehand'=>'Freehand','Fresca'=>'Fresca','Frijole'=>'Frijole','Fruktur'=>'Fruktur','Fugaz One'=>'Fugaz One','GFS Didot'=>'GFS Didot','GFS Neohellenic'=>'GFS Neohellenic','Gabriela'=>'Gabriela','Gaegu'=>'Gaegu','Gafata'=>'Gafata','Galada'=>'Galada','Galdeano'=>'Galdeano','Galindo'=>'Galindo','Gamja Flower'=>'Gamja Flower','Gayathri'=>'Gayathri','Gelasio'=>'Gelasio','Gentium Basic'=>'Gentium Basic','Gentium Book Basic'=>'Gentium Book Basic','Geo'=>'Geo','Geostar'=>'Geostar','Geostar Fill'=>'Geostar Fill','Germania One'=>'Germania One','Gidugu'=>'Gidugu','Gilda Display'=>'Gilda Display','Girassol'=>'Girassol','Give You Glory'=>'Give You Glory','Glass Antiqua'=>'Glass Antiqua','Glegoo'=>'Glegoo','Gloria Hallelujah'=>'Gloria Hallelujah','Goblin One'=>'Goblin One','Gochi Hand'=>'Gochi Hand','Gorditas'=>'Gorditas','Gothic A1'=>'Gothic A1','Goudy Bookletter 1911'=>'Goudy Bookletter 1911','Graduate'=>'Graduate','Grand Hotel'=>'Grand Hotel','Gravitas One'=>'Gravitas One','Great Vibes'=>'Great Vibes','Grenze'=>'Grenze','Griffy'=>'Griffy','Gruppo'=>'Gruppo','Gudea'=>'Gudea','Gugi'=>'Gugi','Gupter'=>'Gupter','Gurajada'=>'Gurajada','Habibi'=>'Habibi','Halant'=>'Halant','Hammersmith One'=>'Hammersmith One','Hanalei'=>'Hanalei','Hanalei Fill'=>'Hanalei Fill','Handlee'=>'Handlee','Hanuman'=>'Hanuman','Happy Monkey'=>'Happy Monkey','Harmattan'=>'Harmattan','Headland One'=>'Headland One','Heebo'=>'Heebo','Henny Penny'=>'Henny Penny','Hepta Slab'=>'Hepta Slab','Herr Von Muellerhoff'=>'Herr Von Muellerhoff','Hi Melody'=>'Hi Melody','Hind'=>'Hind','Hind Guntur'=>'Hind Guntur','Hind Madurai'=>'Hind Madurai','Hind Siliguri'=>'Hind Siliguri','Hind Vadodara'=>'Hind Vadodara','Holtwood One SC'=>'Holtwood One SC','Homemade Apple'=>'Homemade Apple','Homenaje'=>'Homenaje','IBM Plex Mono'=>'IBM Plex Mono','IBM Plex Sans'=>'IBM Plex Sans','IBM Plex Sans Condensed'=>'IBM Plex Sans Condensed','IBM Plex Serif'=>'IBM Plex Serif','IM Fell DW Pica'=>'IM Fell DW Pica','IM Fell DW Pica SC'=>'IM Fell DW Pica SC','IM Fell Double Pica'=>'IM Fell Double Pica','IM Fell Double Pica SC'=>'IM Fell Double Pica SC','IM Fell English'=>'IM Fell English','IM Fell English SC'=>'IM Fell English SC','IM Fell French Canon'=>'IM Fell French Canon','IM Fell French Canon SC'=>'IM Fell French Canon SC','IM Fell Great Primer'=>'IM Fell Great Primer','IM Fell Great Primer SC'=>'IM Fell Great Primer SC','Ibarra Real Nova'=>'Ibarra Real Nova','Iceberg'=>'Iceberg','Iceland'=>'Iceland','Imprima'=>'Imprima','Inconsolata'=>'Inconsolata','Inder'=>'Inder','Indie Flower'=>'Indie Flower','Inika'=>'Inika','Inknut Antiqua'=>'Inknut Antiqua','Inria Serif'=>'Inria Serif','Irish Grover'=>'Irish Grover','Istok Web'=>'Istok Web','Italiana'=>'Italiana','Italianno'=>'Italianno','Itim'=>'Itim','Jacques Francois'=>'Jacques Francois','Jacques Francois Shadow'=>'Jacques Francois Shadow','Jaldi'=>'Jaldi','Jim Nightshade'=>'Jim Nightshade','Jockey One'=>'Jockey One','Jolly Lodger'=>'Jolly Lodger','Jomhuria'=>'Jomhuria','Jomolhari'=>'Jomolhari','Josefin Sans'=>'Josefin Sans','Josefin Slab'=>'Josefin Slab','Joti One'=>'Joti One','Jua'=>'Jua','Judson'=>'Judson','Julee'=>'Julee','Julius Sans One'=>'Julius Sans One','Junge'=>'Junge','Jura'=>'Jura','Just Another Hand'=>'Just Another Hand','Just Me Again Down Here'=>'Just Me Again Down Here','K2D'=>'K2D','Kadwa'=>'Kadwa','Kalam'=>'Kalam','Kameron'=>'Kameron','Kanit'=>'Kanit','Kantumruy'=>'Kantumruy','Karla'=>'Karla','Karma'=>'Karma','Katibeh'=>'Katibeh','Kaushan Script'=>'Kaushan Script','Kavivanar'=>'Kavivanar','Kavoon'=>'Kavoon','Kdam Thmor'=>'Kdam Thmor','Keania One'=>'Keania One','Kelly Slab'=>'Kelly Slab','Kenia'=>'Kenia','Khand'=>'Khand','Khmer'=>'Khmer','Khula'=>'Khula','Kirang Haerang'=>'Kirang Haerang','Kite One'=>'Kite One','Knewave'=>'Knewave','KoHo'=>'KoHo','Kodchasan'=>'Kodchasan','Kosugi'=>'Kosugi','Kosugi Maru'=>'Kosugi Maru','Kotta One'=>'Kotta One','Koulen'=>'Koulen','Kranky'=>'Kranky','Kreon'=>'Kreon','Kristi'=>'Kristi','Krona One'=>'Krona One','Krub'=>'Krub','Kulim Park'=>'Kulim Park','Kumar One'=>'Kumar One','Kumar One Outline'=>'Kumar One Outline','Kurale'=>'Kurale','La Belle Aurore'=>'La Belle Aurore','Lacquer'=>'Lacquer','Laila'=>'Laila','Lakki Reddy'=>'Lakki Reddy','Lalezar'=>'Lalezar','Lancelot'=>'Lancelot','Lateef'=>'Lateef','Lato'=>'Lato','League Script'=>'League Script','Leckerli One'=>'Leckerli One','Ledger'=>'Ledger','Lekton'=>'Lekton','Lemon'=>'Lemon','Lemonada'=>'Lemonada','Lexend Deca'=>'Lexend Deca','Lexend Exa'=>'Lexend Exa','Lexend Giga'=>'Lexend Giga','Lexend Mega'=>'Lexend Mega','Lexend Peta'=>'Lexend Peta','Lexend Tera'=>'Lexend Tera','Lexend Zetta'=>'Lexend Zetta','Libre Barcode 128'=>'Libre Barcode 128','Libre Barcode 128 Text'=>'Libre Barcode 128 Text','Libre Barcode 39'=>'Libre Barcode 39','Libre Barcode 39 Extended'=>'Libre Barcode 39 Extended','Libre Barcode 39 Extended Text'=>'Libre Barcode 39 Extended Text','Libre Barcode 39 Text'=>'Libre Barcode 39 Text','Libre Baskerville'=>'Libre Baskerville','Libre Caslon Display'=>'Libre Caslon Display','Libre Caslon Text'=>'Libre Caslon Text','Libre Franklin'=>'Libre Franklin','Life Savers'=>'Life Savers','Lilita One'=>'Lilita One','Lily Script One'=>'Lily Script One','Limelight'=>'Limelight','Linden Hill'=>'Linden Hill','Literata'=>'Literata','Liu Jian Mao Cao'=>'Liu Jian Mao Cao','Livvic'=>'Livvic','Lobster'=>'Lobster','Lobster Two'=>'Lobster Two','Londrina Outline'=>'Londrina Outline','Londrina Shadow'=>'Londrina Shadow','Londrina Sketch'=>'Londrina Sketch','Londrina Solid'=>'Londrina Solid','Long Cang'=>'Long Cang','Lora'=>'Lora','Love Ya Like A Sister'=>'Love Ya Like A Sister','Loved by the King'=>'Loved by the King','Lovers Quarrel'=>'Lovers Quarrel','Luckiest Guy'=>'Luckiest Guy','Lusitana'=>'Lusitana','Lustria'=>'Lustria','M PLUS 1p'=>'M PLUS 1p','M PLUS Rounded 1c'=>'M PLUS Rounded 1c','Ma Shan Zheng'=>'Ma Shan Zheng','Macondo'=>'Macondo','Macondo Swash Caps'=>'Macondo Swash Caps','Mada'=>'Mada','Magra'=>'Magra','Maiden Orange'=>'Maiden Orange','Maitree'=>'Maitree','Major Mono Display'=>'Major Mono Display','Mako'=>'Mako','Mali'=>'Mali','Mallanna'=>'Mallanna','Mandali'=>'Mandali','Manjari'=>'Manjari','Mansalva'=>'Mansalva','Manuale'=>'Manuale','Marcellus'=>'Marcellus','Marcellus SC'=>'Marcellus SC','Marck Script'=>'Marck Script','Margarine'=>'Margarine','Markazi Text'=>'Markazi Text','Marko One'=>'Marko One','Marmelad'=>'Marmelad','Martel'=>'Martel','Martel Sans'=>'Martel Sans','Marvel'=>'Marvel','Mate'=>'Mate','Mate SC'=>'Mate SC','Maven Pro'=>'Maven Pro','McLaren'=>'McLaren','Meddon'=>'Meddon','MedievalSharp'=>'MedievalSharp','Medula One'=>'Medula One','Meera Inimai'=>'Meera Inimai','Megrim'=>'Megrim','Meie Script'=>'Meie Script','Merienda'=>'Merienda','Merienda One'=>'Merienda One','Merriweather'=>'Merriweather','Merriweather Sans'=>'Merriweather Sans','Metal'=>'Metal','Metal Mania'=>'Metal Mania','Metamorphous'=>'Metamorphous','Metrophobic'=>'Metrophobic','Michroma'=>'Michroma','Milonga'=>'Milonga','Miltonian'=>'Miltonian','Miltonian Tattoo'=>'Miltonian Tattoo','Mina'=>'Mina','Miniver'=>'Miniver','Miriam Libre'=>'Miriam Libre','Mirza'=>'Mirza','Miss Fajardose'=>'Miss Fajardose','Mitr'=>'Mitr','Modak'=>'Modak','Modern Antiqua'=>'Modern Antiqua','Mogra'=>'Mogra','Molengo'=>'Molengo','Molleitalic'=>'Molleitalic','Monda'=>'Monda','Monofett'=>'Monofett','Monoton'=>'Monoton','Monsieur La Doulaise'=>'Monsieur La Doulaise','Montaga'=>'Montaga','Montez'=>'Montez','Montserrat'=>'Montserrat','Montserrat Alternates'=>'Montserrat Alternates','Montserrat Subrayada'=>'Montserrat Subrayada','Moul'=>'Moul','Moulpali'=>'Moulpali','Mountains of Christmas'=>'Mountains of Christmas','Mouse Memoirs'=>'Mouse Memoirs','Mr Bedfort'=>'Mr Bedfort','Mr Dafoe'=>'Mr Dafoe','Mr De Haviland'=>'Mr De Haviland','Mrs Saint Delafield'=>'Mrs Saint Delafield','Mrs Sheppards'=>'Mrs Sheppards','Mukta'=>'Mukta','Mukta Mahee'=>'Mukta Mahee','Mukta Malar'=>'Mukta Malar','Mukta Vaani'=>'Mukta Vaani','Muli'=>'Muli','Mystery Quest'=>'Mystery Quest','NTR'=>'NTR','Nanum Brush Script'=>'Nanum Brush Script','Nanum Gothic'=>'Nanum Gothic','Nanum Gothic Coding'=>'Nanum Gothic Coding','Nanum Myeongjo'=>'Nanum Myeongjo','Nanum Pen Script'=>'Nanum Pen Script','Neucha'=>'Neucha','Neuton'=>'Neuton','New Rocker'=>'New Rocker','News Cycle'=>'News Cycle','Niconne'=>'Niconne','Niramit'=>'Niramit','Nixie One'=>'Nixie One','Nobile'=>'Nobile','Nokora'=>'Nokora','Norican'=>'Norican','Nosifer'=>'Nosifer','Notable'=>'Notable','Nothing You Could Do'=>'Nothing You Could Do','Noticia Text'=>'Noticia Text','Noto Sans'=>'Noto Sans','Noto Sans HK'=>'Noto Sans HK','Noto Sans JP'=>'Noto Sans JP','Noto Sans KR'=>'Noto Sans KR','Noto Sans SC'=>'Noto Sans SC','Noto Sans TC'=>'Noto Sans TC','Noto Serif'=>'Noto Serif','Noto Serif JP'=>'Noto Serif JP','Noto Serif KR'=>'Noto Serif KR','Noto Serif SC'=>'Noto Serif SC','Noto Serif TC'=>'Noto Serif TC','Nova Cut'=>'Nova Cut','Nova Flat'=>'Nova Flat','Nova Mono'=>'Nova Mono','Nova Oval'=>'Nova Oval','Nova Round'=>'Nova Round','Nova Script'=>'Nova Script','Nova Slim'=>'Nova Slim','Nova Square'=>'Nova Square','Numans'=>'Numans','Nunito'=>'Nunito','Nunito Sans'=>'Nunito Sans','Odibee Sans'=>'Odibee Sans','Odor Mean Chey'=>'Odor Mean Chey','Offside'=>'Offside','Old Standard TT'=>'Old Standard TT','Oldenburg'=>'Oldenburg','Oleo Script'=>'Oleo Script','Oleo Script Swash Caps'=>'Oleo Script Swash Caps','Open Sans'=>'Open Sans','Open Sans Condensed'=>'Open Sans Condensed','Oranienbaum'=>'Oranienbaum','Orbitron'=>'Orbitron','Oregano'=>'Oregano','Orienta'=>'Orienta','Original Surfer'=>'Original Surfer','Oswald'=>'Oswald','Over the Rainbow'=>'Over the Rainbow','Overlock'=>'Overlock','Overlock SC'=>'Overlock SC','Overpass'=>'Overpass','Overpass Mono'=>'Overpass Mono','Ovo'=>'Ovo','Oxygen'=>'Oxygen','Oxygen Mono'=>'Oxygen Mono','PT Mono'=>'PT Mono','PT Sans'=>'PT Sans','PT Sans Caption'=>'PT Sans Caption','PT Sans Narrow'=>'PT Sans Narrow','PT Serif'=>'PT Serif','PT Serif Caption'=>'PT Serif Caption','Pacifico'=>'Pacifico','Padauk'=>'Padauk','Palanquin'=>'Palanquin','Palanquin Dark'=>'Palanquin Dark','Pangolin'=>'Pangolin','Paprika'=>'Paprika','Parisienne'=>'Parisienne','Passero One'=>'Passero One','Passion One'=>'Passion One','Pathway Gothic One'=>'Pathway Gothic One','Patrick Hand'=>'Patrick Hand','Patrick Hand SC'=>'Patrick Hand SC','Pattaya'=>'Pattaya','Patua One'=>'Patua One','Pavanam'=>'Pavanam','Paytone One'=>'Paytone One','Peddana'=>'Peddana','Peralta'=>'Peralta','Permanent Marker'=>'Permanent Marker','Petit Formal Script'=>'Petit Formal Script','Petrona'=>'Petrona','Philosopher'=>'Philosopher','Piedra'=>'Piedra','Pinyon Script'=>'Pinyon Script','Pirata One'=>'Pirata One','Plaster'=>'Plaster','Play'=>'Play','Playball'=>'Playball','Playfair Display'=>'Playfair Display','Playfair Display SC'=>'Playfair Display SC','Podkova'=>'Podkova','Poiret One'=>'Poiret One','Poller One'=>'Poller One','Poly'=>'Poly','Pompiere'=>'Pompiere','Pontano Sans'=>'Pontano Sans','Poor Story'=>'Poor Story','Poppins'=>'Poppins','Port Lligat Sans'=>'Port Lligat Sans','Port Lligat Slab'=>'Port Lligat Slab','Pragati Narrow'=>'Pragati Narrow','Prata'=>'Prata','Preahvihear'=>'Preahvihear','Press Start 2P'=>'Press Start 2P','Pridi'=>'Pridi','Princess Sofia'=>'Princess Sofia','Prociono'=>'Prociono','Prompt'=>'Prompt','Prosto One'=>'Prosto One','Proza Libre'=>'Proza Libre','Public Sans'=>'Public Sans','Puritan'=>'Puritan','Purple Purse'=>'Purple Purse','Quando'=>'Quando','Quantico'=>'Quantico','Quattrocento'=>'Quattrocento','Quattrocento Sans'=>'Quattrocento Sans','Questrial'=>'Questrial','Quicksand'=>'Quicksand','Quintessential'=>'Quintessential','Qwigley'=>'Qwigley','Racing Sans One'=>'Racing Sans One','Radley'=>'Radley','Rajdhani'=>'Rajdhani','Rakkas'=>'Rakkas','Raleway'=>'Raleway','Raleway Dots'=>'Raleway Dots','Ramabhadra'=>'Ramabhadra','Ramaraja'=>'Ramaraja','Rambla'=>'Rambla','Rammetto One'=>'Rammetto One','Ranchers'=>'Ranchers','Rancho'=>'Rancho','Ranga'=>'Ranga','Rasa'=>'Rasa','Rationale'=>'Rationale','Ravi Prakash'=>'Ravi Prakash','Red Hat Display'=>'Red Hat Display','Red Hat Text'=>'Red Hat Text','Redressed'=>'Redressed','Reem Kufi'=>'Reem Kufi','Reenie Beanie'=>'Reenie Beanie','Revalia'=>'Revalia','Rhodium Libre'=>'Rhodium Libre','Ribeye'=>'Ribeye','Ribeye Marrow'=>'Ribeye Marrow','Righteous'=>'Righteous','Risque'=>'Risque','Roboto'=>'Roboto','Roboto Condensed'=>'Roboto Condensed','Roboto Mono'=>'Roboto Mono','Roboto Slab'=>'Roboto Slab','Rochester'=>'Rochester','Rock Salt'=>'Rock Salt','Rokkitt'=>'Rokkitt','Romanesco'=>'Romanesco','Ropa Sans'=>'Ropa Sans','Rosario'=>'Rosario','Rosarivo'=>'Rosarivo','Rouge Script'=>'Rouge Script','Rozha One'=>'Rozha One','Rubik'=>'Rubik','Rubik Mono One'=>'Rubik Mono One','Ruda'=>'Ruda','Rufina'=>'Rufina','Ruge Boogie'=>'Ruge Boogie','Ruluko'=>'Ruluko','Rum Raisin'=>'Rum Raisin','Ruslan Display'=>'Ruslan Display','Russo One'=>'Russo One','Ruthie'=>'Ruthie','Rye'=>'Rye','Sacramento'=>'Sacramento','Sahitya'=>'Sahitya','Sail'=>'Sail','Saira'=>'Saira','Saira Condensed'=>'Saira Condensed','Saira Extra Condensed'=>'Saira Extra Condensed','Saira Semi Condensed'=>'Saira Semi Condensed','Saira Stencil One'=>'Saira Stencil One','Salsa'=>'Salsa','Sanchez'=>'Sanchez','Sancreek'=>'Sancreek','Sansita'=>'Sansita','Sarabun'=>'Sarabun','Sarala'=>'Sarala','Sarina'=>'Sarina','Sarpanch'=>'Sarpanch','Satisfy'=>'Satisfy','Sawarabi Gothic'=>'Sawarabi Gothic','Sawarabi Mincho'=>'Sawarabi Mincho','Scada'=>'Scada','Scheherazade'=>'Scheherazade','Schoolbell'=>'Schoolbell','Scope One'=>'Scope One','Seaweed Script'=>'Seaweed Script','Secular One'=>'Secular One','Sedgwick Ave'=>'Sedgwick Ave','Sedgwick Ave Display'=>'Sedgwick Ave Display','Sevillana'=>'Sevillana','Seymour One'=>'Seymour One','Shadows Into Light'=>'Shadows Into Light','Shadows Into Light Two'=>'Shadows Into Light Two','Shanti'=>'Shanti','Share'=>'Share','Share Tech'=>'Share Tech','Share Tech Mono'=>'Share Tech Mono','Shojumaru'=>'Shojumaru','Short Stack'=>'Short Stack','Shrikhand'=>'Shrikhand','Siemreap'=>'Siemreap','Sigmar One'=>'Sigmar One','Signika'=>'Signika','Signika Negative'=>'Signika Negative','Simonetta'=>'Simonetta','Single Day'=>'Single Day','Sintony'=>'Sintony','Sirin Stencil'=>'Sirin Stencil','Six Caps'=>'Six Caps','Skranji'=>'Skranji','Slabo 13px'=>'Slabo 13px','Slabo 27px'=>'Slabo 27px','Slackey'=>'Slackey','Smokum'=>'Smokum','Smythe'=>'Smythe','Sniglet'=>'Sniglet','Snippet'=>'Snippet','Snowburst One'=>'Snowburst One','Sofadi One'=>'Sofadi One','Sofia'=>'Sofia','Solway'=>'Solway','Song Myung'=>'Song Myung','Sonsie One'=>'Sonsie One','Sorts Mill Goudy'=>'Sorts Mill Goudy','Source Code Pro'=>'Source Code Pro','Source Sans Pro'=>'Source Sans Pro','Source Serif Pro'=>'Source Serif Pro','Space Mono'=>'Space Mono','Special Elite'=>'Special Elite','Spectral'=>'Spectral','Spectral SC'=>'Spectral SC','Spicy Rice'=>'Spicy Rice','Spinnaker'=>'Spinnaker','Spirax'=>'Spirax','Squada One'=>'Squada One','Sree Krushnadevaraya'=>'Sree Krushnadevaraya','Sriracha'=>'Sriracha','Srisakdi'=>'Srisakdi','Staatliches'=>'Staatliches','Stalemate'=>'Stalemate','Stalinist One'=>'Stalinist One','Stardos Stencil'=>'Stardos Stencil','Stint Ultra Condensed'=>'Stint Ultra Condensed','Stint Ultra Expanded'=>'Stint Ultra Expanded','Stoke'=>'Stoke','Strait'=>'Strait','Stylish'=>'Stylish','Sue Ellen Francisco'=>'Sue Ellen Francisco','Suez One'=>'Suez One','Sulphur Point'=>'Sulphur Point','Sumana'=>'Sumana','Sunflower'=>'Sunflower','Sunshiney'=>'Sunshiney','Supermercado One'=>'Supermercado One','Sura'=>'Sura','Suranna'=>'Suranna','Suravaram'=>'Suravaram','Suwannaphum'=>'Suwannaphum','Swanky and Moo Moo'=>'Swanky and Moo Moo','Syncopate'=>'Syncopate','Tajawal'=>'Tajawal','Tangerine'=>'Tangerine','Taprom'=>'Taprom','Tauri'=>'Tauri','Taviraj'=>'Taviraj','Teko'=>'Teko','Telex'=>'Telex','Tenali Ramakrishna'=>'Tenali Ramakrishna','Tenor Sans'=>'Tenor Sans','Text Me One'=>'Text Me One','Thasadith'=>'Thasadith','The Girl Next Door'=>'The Girl Next Door','Tienne'=>'Tienne','Tillana'=>'Tillana','Timmana'=>'Timmana','Tinos'=>'Tinos','Titan One'=>'Titan One','Titillium Web'=>'Titillium Web','Tomorrow'=>'Tomorrow','Trade Winds'=>'Trade Winds','Trirong'=>'Trirong','Trocchi'=>'Trocchi','Trochut'=>'Trochut','Trykker'=>'Trykker','Tulpen One'=>'Tulpen One','Turret Road'=>'Turret Road','Ubuntu'=>'Ubuntu','Ubuntu Condensed'=>'Ubuntu Condensed','Ubuntu Mono'=>'Ubuntu Mono','Ultra'=>'Ultra','Uncial Antiqua'=>'Uncial Antiqua','Underdog'=>'Underdog','Unica One'=>'Unica One','UnifrakturCook'=>'UnifrakturCook','UnifrakturMaguntia'=>'UnifrakturMaguntia','Unkempt'=>'Unkempt','Unlock'=>'Unlock','Unna'=>'Unna','VT323'=>'VT323','Vampiro One'=>'Vampiro One','Varela'=>'Varela','Varela Round'=>'Varela Round','Vast Shadow'=>'Vast Shadow','Vesper Libre'=>'Vesper Libre','Vibes'=>'Vibes','Vibur'=>'Vibur','Vidaloka'=>'Vidaloka','Viga'=>'Viga','Voces'=>'Voces','Volkhov'=>'Volkhov','Vollkorn'=>'Vollkorn','Vollkorn SC'=>'Vollkorn SC','Voltaire'=>'Voltaire','Waiting for the Sunrise'=>'Waiting for the Sunrise','Wallpoet'=>'Wallpoet','Walter Turncoat'=>'Walter Turncoat','Warnes'=>'Warnes','Wellfleet'=>'Wellfleet','Wendy One'=>'Wendy One','Wire One'=>'Wire One','Work Sans'=>'Work Sans','Yanone Kaffeesatz'=>'Yanone Kaffeesatz','Yantramanav'=>'Yantramanav','Yatra One'=>'Yatra One','Yellowtail'=>'Yellowtail','Yeon Sung'=>'Yeon Sung','Yeseva One'=>'Yeseva One','Yesteryear'=>'Yesteryear','Yrsa'=>'Yrsa','ZCOOL KuaiLe'=>'ZCOOL KuaiLe','ZCOOL QingKe HuangYou'=>'ZCOOL QingKe HuangYou','ZCOOL XiaoWei'=>'ZCOOL XiaoWei','Zeyada'=>'Zeyada','Zhi Mang Xing'=>'Zhi Mang Xing','Zilla Slab'=>'Zilla Slab','Zilla Slab Highlight'=>'Zilla Slab Highlight');

    if ( $json ) {
      return wp_send_json($initial + $google_fonts);
    }
    else {
      return $google_fonts;
    }
  }

  /**
   * Get all used fonts.
   *
   * @return array
   */
  public static function get_used_fonts() {
    $used_fonts = array();
    $google_fonts = self::get_fonts(false);

    $options = [];
    $all_options = wp_load_alloptions();
    foreach ( $all_options as $name => $value ) {
      if ( strpos( $name, 'reacg_options' ) === 0 ) {
        $options[ $name ] = maybe_unserialize( $value );
      }
    }
    if ( $options ) {
      foreach ( $options as $option_value ) {
        if ( isset($option_value) ) {
          $option = json_decode($option_value, TRUE);
          foreach ( $option as $value ) {
            if ( is_array($value) ) {
              foreach ( $value as $val ) {
                // Get all font families saved in the DB.
                if ( is_string($val) && in_array($val, $google_fonts) ) {
                  $used_fonts[$val] = $val;
                }
              }
            }
            elseif ( is_string($value) && in_array($value, $google_fonts) ) {
              // Get all font families saved in the DB.
              $used_fonts[$value] = $value;
            }
          }
        }
      }
    }

    return $used_fonts;
  }

  /**
   * Container with necessary rest routs.
   *
   * @param $gallery_id
   * @param $enable_options
   *
   * @return void
   */
  public static function get_rest_routs($gallery_id, $enable_options = FALSE) {
    REACGLibrary::enqueue_scripts();

    $data = REACGLibrary::get_data($gallery_id);

    ?>
    <script>if (typeof reacg_data === "undefined") { var reacg_data = {}; } reacg_data[<?php echo (int) $gallery_id; ?>] = <?php echo wp_json_encode($data);  ?>;</script>
    <div id="reacg-root<?php echo esc_attr((int) $gallery_id); ?>"
         class="reacg-wrapper reacg-gallery reacg-preview"
         data-options-section="<?php echo esc_attr( (int) ($enable_options || is_admin())); ?>"
         data-options-container="<?php echo esc_attr('#reacg_settings'); ?>"
         data-plugin-version="<?php echo esc_attr(REACG_VERSION); ?>"
         data-gallery-timestamp="<?php echo esc_attr(get_post_meta( $gallery_id, 'gallery_timestamp', TRUE )); ?>"
         data-options-timestamp="<?php echo esc_attr(get_post_meta( $gallery_id, 'options_timestamp', TRUE )); ?>"
         data-gallery-id="<?php echo esc_attr((int) $gallery_id); ?>"></div><?php
  }

  /**
   * @return void
   */
  public static function enqueue_scripts() {
    wp_enqueue_style(REACG_PREFIX . '_general');
    wp_enqueue_script(REACG_PREFIX . '_thumbnails');
  }

  public static function get_data($gallery_id) {
    require_once REACG()->plugin_dir . "/includes/gallery.php";
    $gallery = new REACG_Gallery(REACG(), FALSE);
    require_once REACG()->plugin_dir . "/includes/options.php";
    $options = new REACG_Options(TRUE);
    $gallery_options = $options->get_options( $gallery_id );
    $gallery_data = $gallery->get_images( $gallery_id, $gallery_options );
    return [
      'images' => $gallery_data['images'],
      'options' => $gallery_options,
      'imagesCount' => $gallery_data['count'],
    ];
  }

  /**
   * Get all galleries ids.
   *
   * @return int[]|WP_Post[]
   */
  public static function get_galleries() {
    return get_posts(array(
                               'posts_per_page' => -1,
                               'post_type' => 'reacg',
                               'fields' => 'ids',
                             ));
  }

  /**
   * Get a shortcode for the given gallery ID.
   *
   * @param $obj
   * @param $id
   *
   * @return bool|string
   */
  public static function get_shortcode($obj, $id) {
    if ( !$id ) {
      return false;
    }

    return '[' . $obj->shortcode . ' id="' . $id . '"]';
  }

  /**
   * Get shortcodes list.
   *
   * @param      $obj
   * @param bool $include_empty
   * @param bool $associative
   *
   * @return array|false|string
   */
  public static function get_shortcodes( $obj, bool $include_empty = FALSE, $associative = TRUE) {
    $posts = get_posts(array(
                         'posts_per_page' => -1,
                         'post_type' => 'reacg',
                       ));
    $data = [];
    if ( $associative ) {
      $key_shifter = 0;
      if ( $include_empty ) {
        $data[$key_shifter] = [];
        $data[$key_shifter]['id'] = 0;
        $data[$key_shifter]['title'] = __('Select gallery', 'regallery');
        $data[$key_shifter]['shortcode'] = '';
        ++$key_shifter;
      }
      if ( !empty($posts) ) {
        $data[$key_shifter] = [];
        $data[$key_shifter]['id'] = -1;
        $data[$key_shifter]['title'] = __('All Images', 'regallery');
        $data[$key_shifter]['shortcode'] = self::get_shortcode($obj, -1);
        ++$key_shifter;
      }

      foreach ( $posts as $key => $post ) {
        $data[$key + $key_shifter] = [];
        $data[$key + $key_shifter]['id'] = $post->ID;
        $data[$key + $key_shifter]['title'] = $post->post_title ? $post->post_title : __('(no title)', 'regallery');
        $data[$key + $key_shifter]['shortcode'] = self::get_shortcode($obj, $post->ID);
      }

      return json_encode($data);
    }
    else {
      if ( $include_empty ) {
        $data[0] = __('Select gallery', 'regallery');
      }
      if ( !empty($posts) ) {
        $data[-1] = __('All Images', 'regallery');
      }
      foreach ( $posts as $key => $post ) {
        $data[$post->ID] = $post->post_title ? $post->post_title : __('(no title)', 'regallery');
      }

      return $data;
    }
  }

  /**
   * Return valid gallery ID or throw error.
   *
   * @param $request
   * @param $param
   *
   * @return int|null
   */
  public static function get_gallery_id($request, $param) {
    $parameters = $request->get_url_params();

    if ( !isset($parameters[$param]) ) {
      return wp_send_json(new WP_Error( 'missing_gallery', __( 'Missing gallery ID.', 'regallery' ), array( 'status' => 400 ) ), 400);
    }
    $gallery_id = (int) $parameters[$param];

    if ( $gallery_id !== -1 && $gallery_id !== 0 && get_post_status( $gallery_id ) === FALSE ) {
      return wp_send_json(new WP_Error( 'wrong_gallery', __( 'There is no such a gallery.', 'regallery' ), array( 'status' => 400 ) ), 400);
    }

    return $gallery_id;
  }

  /**
   * Get/set the plugin installed time.
   *
   * @return false|int|mixed|null
   */
  public static function installed_time() {
    $installed_time = get_option( 'reacg_installed_time' );

    if ( ! $installed_time ) {
      $installed_time = time();

      update_option( 'reacg_installed_time', $installed_time );
    }

    return $installed_time;
  }
}
