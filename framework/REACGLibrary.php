<?php

class REACGLibrary {
  public static function allowed_svg() {
    return [
      'svg' => [
        'width'   => true,
        'height'  => true,
        'viewbox' => true,
        'xmlns'   => true,
        'style'   => true,
      ],
      'g' => [],
      'polygon' => [
        'fill'   => true,
        'points' => true,
      ],
      'circle' => [
        'fill' => true,
        'cx'   => true,
        'cy'   => true,
        'r'    => true,
      ],
      'path' => [
        'fill' => true,
        'd'    => true,
        'transform' => true,
      ],
    ];
  }

  public static $pro_icon = '<svg style="margin-left: 5px;" width="38px" height="22px" viewBox="0 0 153 88" xmlns="http://www.w3.org/2000/svg"><path d="M0 0 C0.96301025 -0.00571014 1.92602051 -0.01142029 2.91821289 -0.01730347 C3.9681694 -0.01842133 5.01812592 -0.01953918 6.09989929 -0.02069092 C7.7664003 -0.02813828 7.7664003 -0.02813828 9.46656799 -0.03573608 C13.14472573 -0.05035013 16.82284033 -0.05715834 20.50102234 -0.06268311 C23.05686443 -0.068435 25.61270652 -0.07419273 28.16854858 -0.07995605 C32.84420199 -0.0891209 37.51984246 -0.0954222 42.19550258 -0.09845281 C49.06655064 -0.10293151 55.93739957 -0.12047407 62.8083871 -0.1494534 C68.76923192 -0.17371706 74.7300274 -0.18139917 80.69092369 -0.18312836 C83.22224703 -0.1861544 85.75356953 -0.19418128 88.28486061 -0.20731354 C91.82752669 -0.2243814 95.3697087 -0.22249169 98.91239929 -0.21600342 C99.95628311 -0.2252182 101.00016693 -0.23443298 102.07568359 -0.243927 C110.4889872 -0.19569598 116.51764793 1.76531262 123.20024109 7.14044189 C127.39491321 12.62404291 129.29930016 17.23715251 129.37724304 24.14923096 C129.3964959 25.641633 129.3964959 25.641633 129.4161377 27.16418457 C129.42352463 28.23400574 129.43091156 29.3038269 129.43852234 30.40606689 C129.44659409 31.51343201 129.45466583 32.62079712 129.46298218 33.76171875 C129.47680719 36.1052291 129.48753642 38.44875945 129.4954071 40.79229736 C129.51260968 44.36316393 129.55651041 47.93310206 129.60063171 51.50372314 C129.61076011 53.78366427 129.61927575 56.06361325 129.62602234 58.34356689 C129.64353043 59.40529114 129.66103851 60.46701538 129.67907715 61.56091309 C129.6558828 69.54887152 128.06137832 74.82754616 122.82524109 80.89044189 C117.11483975 85.78507161 112.88955662 88.25419529 105.4072113 88.28088379 C104.44564621 88.28659393 103.48408112 88.29230408 102.49337769 88.29818726 C100.92079948 88.29986404 100.92079948 88.29986404 99.31645203 88.30157471 C97.65236046 88.30902206 97.65236046 88.30902206 95.95465088 88.31661987 C92.28129965 88.3312351 88.60799162 88.33804267 84.93461609 88.34356689 C82.38116962 88.34931911 79.82772317 88.35507684 77.27427673 88.36083984 C72.60229181 88.37000522 67.93031984 88.3763061 63.2583282 88.3793366 C56.39578004 88.3838132 49.53343147 88.40134549 42.67094398 88.43033719 C36.71544198 88.45461814 30.75998931 88.46228383 24.80443573 88.46401215 C22.27672998 88.46703607 19.74902502 88.4750558 17.22135162 88.48819733 C13.68128424 88.5052877 10.1417004 88.50336987 6.60160828 88.49688721 C5.56131973 88.50610199 4.52103119 88.51531677 3.44921875 88.52481079 C-4.73695785 88.47794814 -11.77427292 87.16592789 -17.79975891 81.14044189 C-21.52396907 75.92865771 -23.90495514 71.66698684 -23.97676086 65.15338135 C-23.9895961 64.13835037 -24.00243134 63.1233194 -24.01565552 62.07752991 C-24.02304245 60.98067368 -24.03042938 59.88381744 -24.03804016 58.75372314 C-24.04611191 57.62274704 -24.05418365 56.49177094 -24.0625 55.32652283 C-24.07633902 52.9299574 -24.08706199 50.53337227 -24.09492493 48.13677979 C-24.11210985 44.48462157 -24.15599599 40.83337509 -24.20014954 37.18145752 C-24.21027911 34.85138604 -24.21879438 32.52130688 -24.22554016 30.19122314 C-24.24304825 29.10448807 -24.26055634 28.01775299 -24.27859497 26.89808655 C-24.25568463 18.82587802 -22.7173398 13.51869344 -17.42475891 7.39044189 C-11.71116305 2.49307401 -7.48604418 0.02666017 0 0 Z " fill="#F5F2FF" transform="translate(23.799758911132813,-0.14044189453125)"></path><path d="M0 0 C3.68615206 2.21937072 5.74805019 4.36011501 7.5 8.375 C8.64966184 15.32771681 7.54721796 21.07085687 4.1328125 27.20703125 C0.8573381 31.55604631 -3.42862989 33.6845433 -8.5 35.375 C-16.14747114 35.94883622 -22.09789337 36.04342219 -28.1875 30.96875 C-31.52710966 27.69098496 -32.91012473 25.15387749 -33.0625 20.4375 C-32.55128788 13.25877663 -31.01166737 8.42930882 -25.625 3.5 C-18.45536973 -2.54498238 -8.6526023 -3.8961061 0 0 Z " fill="#896CFF" transform="translate(125.5,27.625)"></path><path d="M0 0 C3.70818297 -0.08756305 7.41609763 -0.14058015 11.125 -0.1875 C12.17171875 -0.21263672 13.2184375 -0.23777344 14.296875 -0.26367188 C19.96055425 -0.31744098 24.12542151 -0.25792535 29 3 C30 4 30 4 30.3125 7.75 C30.27178656 10.99544251 30.01242062 11.9795947 28.25 14.875 C26.20332225 16.80797343 24.60156414 17.97795695 22 19 C23.32 19.99 24.64 20.98 26 22 C25.47694567 26.40860079 24.87251184 30.63744078 24 35 C19.71 35 15.42 35 11 35 C12 26.42857143 12 26.42857143 13 23 C11.02 23 9.04 23 7 23 C6.79503906 23.69867188 6.59007813 24.39734375 6.37890625 25.1171875 C6.10949219 26.02726562 5.84007813 26.93734375 5.5625 27.875 C5.29566406 28.77992187 5.02882813 29.68484375 4.75390625 30.6171875 C4 33 4 33 3 35 C-0.96 35 -4.92 35 -9 35 C-8.27347664 28.63170882 -6.70010013 22.56410745 -5.0625 16.375 C-4.79759766 15.35148437 -4.53269531 14.32796875 -4.25976562 13.2734375 C-3.06413217 8.71638295 -1.82098461 4.35293175 0 0 Z " fill="#896BFF" transform="translate(62,27)"></path><path d="M0 0 C3.3956697 -0.08745946 6.79104773 -0.14054444 10.1875 -0.1875 C11.14720703 -0.21263672 12.10691406 -0.23777344 13.09570312 -0.26367188 C21.84048429 -0.35444815 21.84048429 -0.35444815 26 3 C26.598125 3.391875 27.19625 3.78375 27.8125 4.1875 C29.83132873 7.26887017 29.57723999 10.50338535 29 14 C26.80597572 18.75371928 24.02862635 21.18964541 19.37109375 23.3203125 C14.91354324 24.59809469 10.60216322 24.23010816 6 24 C5.34 27.63 4.68 31.26 4 35 C-0.29 35 -4.58 35 -9 35 C-8.29824602 30.78947612 -7.51539199 26.92246822 -6.40625 22.83203125 C-6.09816406 21.69185547 -5.79007813 20.55167969 -5.47265625 19.37695312 C-5.15167969 18.20068359 -4.83070312 17.02441406 -4.5 15.8125 C-4.17902344 14.62462891 -3.85804687 13.43675781 -3.52734375 12.21289062 C-1.15788849 3.47366546 -1.15788849 3.47366546 0 0 Z " fill="#8A6CFF" transform="translate(27,27)"></path><path d="M0 0 C2.875 0.75 2.875 0.75 3.875 1.75 C3.82165817 7.84430385 2.38984116 13.23669438 -0.125 18.75 C-3.125 20.75 -3.125 20.75 -5.75 20.4375 C-8.125 19.75 -8.125 19.75 -9.125 18.75 C-9.82749396 11.57645595 -8.88015315 6.81713084 -4.8125 0.8125 C-3.125 -0.25 -3.125 -0.25 0 0 Z " fill="#F0EBFF" transform="translate(116.125,34.25)"></path><path d="M0 0 C1.98 0 3.96 0 6 0 C6.125 2.375 6.125 2.375 6 5 C4 7 4 7 0.875 7.125 C-0.548125 7.063125 -0.548125 7.063125 -2 7 C-1.125 2.25 -1.125 2.25 0 0 Z " fill="#EFEAFF" transform="translate(73,35)"></path><path d="M0 0 C1.65 0.33 3.3 0.66 5 1 C4.67 2.98 4.34 4.96 4 7 C2.02 7.33 0.04 7.66 -2 8 C-1.34 5.36 -0.68 2.72 0 0 Z " fill="#EFEAFF" transform="translate(38,35)"></path></svg>';

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
    ob_start();
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
    return ob_get_clean();
  }

  /**
   * @return void
   */
  public static function enqueue_scripts() {
    wp_enqueue_style(REACG_PREFIX . '_general');
    wp_enqueue_style(REACG_PREFIX . '_thumbnails');
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
                         'post_status' => [ 'publish', 'private' ],
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
