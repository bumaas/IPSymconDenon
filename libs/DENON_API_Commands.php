<?php

declare(strict_types=1);

class DENON_API_Commands extends stdClass
{
    //MAIN Zone
    public const string PW = 'PW'; // Power
    public const string MV = 'MV'; // Master Volume
    public const string BL = 'BL'; // Balance
    //CV
    public const string CVFL  = 'CVFL'; // Channel Volume Front Left
    public const string CVFR  = 'CVFR'; // Channel Volume Front Right
    public const string CVC   = 'CVC'; // Channel Volume Center
    public const string CVSW  = 'CVSW'; // Channel Volume Subwoofer
    public const string CVSW2 = 'CVSW2'; // Channel Volume Subwoofer2
    public const string CVSW3 = 'CVSW3'; // Channel Volume Subwoofer3
    public const string CVSW4 = 'CVSW4'; // Channel Volume Subwoofer4
    public const string CVSL  = 'CVSL'; // Channel Volume Surround Left
    public const string CVSR  = 'CVSR'; // Channel Volume Surround Right
    public const string CVSBL = 'CVSBL'; // Channel Volume Surround Back Left
    public const string CVSBR = 'CVSBR'; // Channel Volume Surround Back Right
    public const string CVSB  = 'CVSB'; // Channel Volume Surround Back
    public const string CVFHL = 'CVFHL'; // Channel Volume Front Height Left
    public const string CVFHR = 'CVFHR'; // Channel Volume Front Height Right
    public const string CVFWL = 'CVFWL'; // Channel Volume Front Wide Left
    public const string CVFWR = 'CVFWR'; // Channel Volume Front Wide Right
    public const string MU    = 'MU'; // Volume Mute
    public const string SI    = 'SI'; // Select Input
    public const string ZM    = 'ZM'; // Main Zone
    public const string SD    = 'SD'; // Select Auto/HDMI/Digital/Analog
    public const string DC    = 'DC'; // Digital Input Mode Select Auto/PCM/DTS
    public const string SV    = 'SV'; // Video Select
    public const string SLP   = 'SLP'; // Main Zone Sleep Timer
    public const string MS    = 'MS'; // Select Surround Mode
    public const string SP    = 'SP'; // Speaker Preset
    public const string MN    = 'MN'; // System
    public const string MSQUICK = 'MSQUICK'; // Quick Select Mode Select (Denon)
    public const string MSQUICKMEMORY = 'MEMORY'; // Quick Select Mode Memory
    public const string MSSMART       = 'MSSMART'; // Smart Select Mode Select (Marantz)

    //MU
    public const string MUON  = 'ON'; // Volume Mute ON
    public const string MUOFF = 'OFF'; // Volume Mute Off

    //VS
    public const string VS    = 'VS'; // Video Setting
    public const string VSASP = 'VSASP'; // ASP
    public const string VSSC  = 'VSSC'; // Set Resolution

    public const string VSSCH   = 'VSSCH'; // Set Resolution HDMI
    public const string VSAUDIO = 'VSAUDIO'; // Set HDMI Audio Output
    public const string VSMONI  = 'VSMONI'; // Set HDMI Monitor
    public const string VSVPM   = 'VSVPM'; // Set Video Processing Mode
    public const string VSVST   = 'VSVST'; // Set Vertical Stretch
    //PS
    public const string PS         = 'PS'; // Parameter Setting
    public const string PSATT      = 'PSATT'; // SW ATT
    public const string PSTONECTRL = 'PSTONE_CTRL'; // Tone Control !da Ident nur Buchstaben und Zahlen enthalten darf, wurde das Blank ersetzt
    public const string PSSB       = 'PSSB'; // Surround Back SP Mode
    public const string PSCINEMAEQ = 'PSCINEMA_EQ'; // Cinema EQ
    public const string PSHTEQ     = 'PSHT_EQ'; // Cinema EQ
    public const string PSMODE     = 'PSMODE'; // Mode Music
    public const string PSDOLVOL   = 'PSDOLVOL'; // Dolby Volume direct change
    public const string PSVOLLEV   = 'PSVOLLEV'; // Dolby Volume Leveler direct change
    public const string PSVOLMOD   = 'PSVOLMOD'; // Dolby Volume Modeler direct change
    public const string PSFH       = 'PSFH'; // FRONT HEIGHT
    public const string PSPHG      = 'PSPHG'; // PL2z HEIGHT GAIN direct change
    public const string PSSP       = 'PSSP'; // Speaker Output set
    public const string PSREFLEV   = 'PSREFLEV'; // Dynamic EQ Reference Level
    public const string PSMULTEQ   = 'PSMULTEQ'; // MultEQ XT 32 mode direct change
    public const string PSDYNEQ  = 'PSDYNEQ'; // Dynamic EQ
    public const string PSLFC    = 'PSLFC'; // Audyssey LFC
    public const string PSDYNVOL = 'PSDYNVOL'; // Dynamic Volume
    public const string PSDSX    = 'PSDSX'; // Audyssey DSX Change
    public const string PSSTW    = 'PSSTW'; // STAGE WIDTH
    public const string PSCNTAMT = 'PSCNTAMT'; // Audyssey Containment Amount
    public const string PSSTH    = 'PSSTH'; // STAGE HEIGHT
    public const string PSBAS    = 'PSBAS'; // BASS
    public const string PSTRE    = 'PSTRE'; // TREBLE
    public const string PSLOM    = 'PSLOM'; // Loudness Management
    public const string PSDRC    = 'PSDRC'; // DRC direct change
    public const string PSMDAX   = 'PSMDAX'; // M-DAX
    public const string PSDCO    = 'PSDCO'; // D.COMP direct change
    public const string PSCLV    = 'PSCLV'; // Center Level Volume
    public const string PSLFE    = 'PSLFE'; // LFE
    public const string PSLFL    = 'PSLFL'; // LFF
    public const string PSEFF  = 'PSEFF'; // EFFECT direct change	Level
    public const string PSDELAY = 'PSDELAY'; // Audio DELAY
    public const string PSDEL   = 'PSDEL'; // DELAY
    public const string PSAFD   = 'PSAFD'; // Auto Flag Detect Mode
    public const string PSPAN   = 'PSPAN'; // PANORAMA
    public const string PSDIM   = 'PSDIM'; // DIMENSION
    public const string PSCEN   = 'PSCEN'; // CENTER WIDTH
    public const string PSCEI   = 'PSCEI'; // CENTER IMAGE
    public const string PSCEG   = 'PSCEG'; // CENTER GAIN
    public const string PSDIC   = 'PSDIC'; // DIALOG CONTROL
    public const string PSRSTR  = 'PSRSTR'; //Audio Restorer
    public const string PSFRONT = 'PSFRONT'; //Front Speaker
    public const string PSRSZ   = 'PSRSZ'; //Room Size
    public const string PSSWR   = 'PSSWR'; //Subwoofer

    public const string BTTX  = 'BTTX'; //Bluetooth Transmitter
    public const string BTLEV = 'BTLEV'; //Bluetooth Level (30..90, 80 = 0 dB)
    public const string SPPR  = 'SPPR'; //Speaker Preset

    //PV
    public const string PV        = 'PV'; // Picture Mode
    public const string PVPICT    = 'PVPICT'; //Picture Mode beim Senden
    public const string PVPICTOFF = 'OFF'; // Picture Mode Off
    public const string PVPICTSTD = 'STD'; // Picture Mode Standard
    public const string PVPICTMOV = 'MOVIE'; // Picture Mode Movie
    public const string PVPICTVVD = 'VVD'; // Picture Mode Vivid
    public const string PVPICTSTM = 'STM'; // Picture Mode Stream
    public const string PVPICTCTM = 'CTM'; // Picture Mode Custom
    public const string PVPICTDAY = 'DAY'; // Picture Mode ISF Day
    public const string PVPICTNGT = 'NGT'; // Picture Mode ISF Night

    public const string PVCN  = 'PVCN'; // Contrast
    public const string PVBR  = 'PVBR'; // Brightness
    public const string PVST  = 'PVST'; // Saturation
    public const string PVCM  = 'PVCM'; // Chroma
    public const string PVHUE = 'PVHUE'; // Hue
    public const string PVENH = 'PVENH'; // Enhancer

    public const string PVDNR    = 'PVDNR'; // Digital Noise Reduction direct change
    public const string PVDNROFF = ' OFF'; // Digital Noise Reduction Off
    public const string PVDNRLOW = ' LOW'; // Digital Noise Reduction Low
    public const string PVDNRMID = ' MID'; // Digital Noise Reduction Middle
    public const string PVDNRHI  = ' HI'; // Digital Noise Reduction High

    // Speaker Setup
    public const string SSSPC    = 'SSSPC';
    public const string SSSPCCEN = 'SSSPCCEN'; // Setup Center
    public const string SSSPCFRO = 'SSSPCFRO'; // Setup Front
    public const string SSSPCSWF = 'SSSPCSWF'; // Setup Subwoofer
    public const string NON      = ' NON'; // none Subwoofer
    public const string SPONE    = ' 1SP'; // Subwoofer 1
    public const string SPTWO    = ' 2SP'; // Subwoofer 2
    public const string SMA      = ' SMA'; // small
    public const string LAR      = ' LAR'; // large

    public const string SR = ' ?'; //Status Request

    //Zone 2
    public const string Z2       = 'Z2'; // Zone 2
    public const string Z2ON     = 'ON'; // Zone 2 On
    public const string Z2OFF    = 'OFF'; // Zone 2 Off
    public const string Z2POWER  = 'Z2POWER'; // Zone 2 Power Z2 beim Senden
    public const string Z2INPUT  = 'Z2INPUT'; // Zone 2 Input Z2 beim Senden
    public const string Z2VOL    = 'Z2VOL'; // Zone 2 Volume Z2 beim Senden
    public const string Z2MU     = 'Z2MU'; // Zone 2 Mute
    public const string Z2CS     = 'Z2CS'; // Zone 2 Channel Setting
    public const string Z2CSST   = 'ST'; // Zone 2 Channel Setting Stereo
    public const string Z2CSMONO = 'MONO'; // Zone 2 Channel Setting Mono
    public const string Z2CVFL   = 'Z2CVFL'; // Zone 2 Channel Volume FL
    public const string Z2CVFR   = 'Z2CVFR'; // Zone 2 Channel Volume FR
    public const string Z2HPF    = 'Z2HPF'; // Zone 2 HPF
    public const string Z2HDA    = 'Z2HDA'; // (nur) Zone 2 HDA
    public const string Z2HDATHR = ' THR'; // (nur) Zone 2 HDA
    public const string Z2HDAPCM = ' PCM'; // (nur) Zone 2 HDA
    public const string Z2PSBAS  = 'Z2PSBAS'; // Zone 2 Parameter Bass
    public const string Z2PSTRE  = 'Z2PSTRE'; // Zone 2 Parameter Treble
    public const string Z2SLP    = 'Z2SLP'; // Zone 2 Sleep Timer
    public const string Z2QUICK  = 'Z2QUICK'; // Zone 2 Quick
    public const string Z2SMART  = 'Z2SMART'; // Zone 2 Smart

    //Zone 3
    public const string Z3       = 'Z3'; // Zone 3
    public const string Z3ON     = 'ON'; // Zone 3 On
    public const string Z3OFF    = 'OFF'; // Zone 3 Off
    public const string Z3POWER  = 'Z3POWER'; // Zone 3 Power Z3 beim Senden
    public const string Z3INPUT  = 'Z3INPUT'; // Zone 3 Input Z3 beim Senden
    public const string Z3VOL    = 'Z3VOL'; // Zone 3 Volume Z3 beim Senden
    public const string Z3MU     = 'Z3MU'; // Zone 3 Mute
    public const string Z3CS     = 'Z3CS'; // Zone 3 Channel Setting
    public const string Z3CSST   = 'ST'; // Zone 3 Channel Setting Stereo
    public const string Z3CSMONO = 'MONO'; // Zone 3 Channel Setting Mono
    public const string Z3CVFL   = 'Z3CVFL'; // Zone 3 Channel Volume FL
    public const string Z3CVFR   = 'Z3CVFR'; // Zone 3 Channel Volume FR
    public const string Z3HPF    = 'Z3HPF'; // Zone 3 HPF
    public const string Z3PSBAS  = 'Z3PSBAS'; // Zone 3 Parameter Bass
    public const string Z3PSTRE  = 'Z3PSTRE'; // Zone 3 Parameter Treble
    public const string Z3SLP    = 'Z3SLP'; // Zone 3 Sleep Timer
    public const string Z3QUICK  = 'Z3QUICK'; // Zone 3 Quick
    public const string Z3SMART  = 'Z3SMART'; // Zone 3 Smart

    public const string NS = 'NS'; // Network Audio
    public const string SY = 'SY'; // Remote Lock
    public const string TR = 'TR'; // Trigger
    public const string UG = 'UG'; // Upgrade ID Display

    // Achtung: SY ist Präfix von SYHPT. SY wird derzeit von keinem Profil benutzt,
    // deshalb gibt es keine Kollision im Empfangspfad. Kommt SY (Remote Lock) später
    // als Profil dazu, muss SYHPT im Katalog davor stehen - wie bei PSDEL/PSDELAY.
    public const string SYHPT     = 'SYHPT'; // HDMI Hot Plug Test
    public const string SYHPTHIGH = ' HIGH'; // Hot Plug Test = High
    public const string SYHPTLOW  = ' LOW'; // Hot Plug Test = Low
    public const string SYHPTTOG  = ' TOG'; // Hot Plug Test = Toggle (Puls High-Low-High)

    public const string CLM    = 'CLM'; // Channel Level Monitoring
    public const string CLMON  = ' ON'; // Channel Level Monitoring On
    public const string CLMOFF = ' OFF'; // Channel Level Monitoring Off

    //Analog Tuner
    public const string TF = 'TF'; // Tuner Frequency

    public const string TPAN     = 'TPAN'; // Tuner Preset (analog)
    public const string TPANUP   = 'UP'; //TUNER PRESET CH UP
    public const string TPANDOWN = 'DOWN'; //TUNER PRESET CH DOWN

    public const string TMAN_BAND = 'TMAN'; // Tuner Mode (analog) Band
    public const string TMANAM    = 'AM'; // Tuner Band AM (Band)
    public const string TMANFM    = 'FM'; // Tuner Band FM (Band)
    public const string TMANDAB   = 'DAB'; // Tuner Band DAB (Band)

    public const string TMAN_MODE  = 'TM'; // Tuner Mode (analog) Mode
    public const string TMANAUTO   = 'ANAUTO'; // Tuner Mode Auto
    public const string TMANMANUAL = 'ANMANUAL'; // Tuner Mode Manual

    //Network Audio
    public const string NSB = 'NSB'; //Direct Preset CH Play 00-55,00=A1,01=A2,B1=08,G8=55

    // Display Network Audio Navigation
    public const string NSUP        = '90'; // Network Audio Cursor Up Control
    public const string NSDOWN      = '91'; // Network Audio Cursor Down Control
    public const string NSLEFT      = '92'; // Network Audio Cursor Left Control
    public const string NSRIGHT     = '93'; // Network Audio Cursor Right Control
    public const string NSENTER     = '94'; // Network Audio Cursor Enter Control
    public const string NSPLAY      = '9A'; // Network Audio Play
    public const string NSPAUSE     = '9B'; // Network Audio Pause
    public const string NSSTOP      = '9C'; // Network Audio Stop
    public const string NSSKIPPLUS  = '9D'; // Network Audio Skip +
    public const string NSSKIPMINUS = '9E'; // Network Audio Skip -
    public const string NSREPEATONE = '9H'; // Network Audio Repeat One
    public const string NSREPEATALL = '9I'; // Network Audio Repeat All
    public const string NSREPEATOFF = '9J'; // Network Audio Repeat Off
    public const string NSRANDOMON  = '9K'; // Network Audio Random On
    public const string NSRANDOMOFF = '9M'; // Network Audio Random Off
    public const string NSTOGGLE    = '9W'; // Network Audio Toggle Switch
    public const string NSPAGENEXT  = '9X'; // Network Audio Page Next
    public const string NSPAGEPREV  = '9Y'; // Network Audio Page Previous

    //Display
    public const string DISPLAY = 'Display'; // Display zur Anzeige
    public const string NSA     = 'NSA'; // Network Audio Extended
    public const string NSA0    = 'NSA0'; // Network Audio Extended Line 0
    public const string NSA1    = 'NSA1'; // Network Audio Extended Line 1
    public const string NSA2    = 'NSA2'; // Network Audio Extended Line 2
    public const string NSA3    = 'NSA3'; // Network Audio Extended Line 3
    public const string NSA4    = 'NSA4'; // Network Audio Extended Line 4
    public const string NSA5    = 'NSA5'; // Network Audio Extended Line 5
    public const string NSA6    = 'NSA6'; // Network Audio Extended Line 6
    public const string NSA7    = 'NSA7'; // Network Audio Extended Line 7
    public const string NSA8    = 'NSA8'; // Network Audio Extended Line 8

    public const string NSE  = 'NSE'; // Network Audio Onscreen Display Information
    public const string NSE0 = 'NSE0'; // Network Audio Onscreen Display Information Line 0
    public const string NSE1 = 'NSE1'; // Network Audio Onscreen Display Information Line 1
    public const string NSE2 = 'NSE2'; // Network Audio Onscreen Display Information Line 2
    public const string NSE3 = 'NSE3'; // Network Audio Onscreen Display Information Line 3
    public const string NSE4 = 'NSE4'; // Network Audio Onscreen Display Information Line 4
    public const string NSE5 = 'NSE5'; // Network Audio Onscreen Display Information Line 5
    public const string NSE6 = 'NSE6'; // Network Audio Onscreen Display Information Line 6
    public const string NSE7 = 'NSE7'; // Network Audio Onscreen Display Information Line 7
    public const string NSE8 = 'NSE8'; // Network Audio Onscreen Display Information Line 8
    public const string NSE9 = 'NSE9'; // Network Audio Onscreen Display Information Line 9

    //SUB Commands

    //PW
    public const string PWON      = 'ON'; // Power On
    public const string PWSTANDBY = 'STANDBY'; // Power Standby
    public const string PWOFF     = 'OFF'; // Power OFF - beim X1200 im XML beobachtet

    //MV
    public const string MVUP   = 'UP'; // Master Volume Up
    public const string MVDOWN = 'DOWN'; // Master Volume Down

    //SI + SV
    public const string IS_PHONO = 'PHONO'; // Select Input Source Phono
    public const string IS_CD    = 'CD'; // Select Input Source CD
    public const string IS_TUNER = 'TUNER'; // Select Input Source Tuner
    public const string IS_FM    = 'FM'; // Select Input Source FM
    public const string IS_DAB   = 'DAB'; // Select Input Source DAB
    public const string IS_DVD   = 'DVD'; // Select Input Source DVD
    public const string IS_HDP   = 'HDP'; // Select Input Source HDP
    public const string IS_BD    = 'BD'; // Select Input Source BD
    public const string IS_BT    = 'BT'; // Select Input Source Blutooth
    public const string IS_MPLAY = 'MPLAY'; // Select Input Source Mediaplayer
    public const string IS_TV    = 'TV'; // Select Input Source TV
    public const string IS_TV_CBL = 'TV/CBL'; // Select Input Source TV/CBL
    public const string IS_SAT_CBL = 'SAT/CBL'; // Select Input Source Sat/CBL
    public const string IS_SAT     = 'SAT'; // Select Input Source Sat
    public const string IS_VCR     = 'VCR'; // Select Input Source VCR
    public const string IS_DVR     = 'DVR'; // Select Input Source DVR
    public const string IS_GAME    = 'GAME'; // Select Input Source Game
    public const string IS_GAME1   = 'GAME1'; // Select Input Source Game1
    public const string IS_GAME2   = 'GAME2'; // Select Input Source Game2
    public const string IS_8K      = '8K'; // Select Input Source 8K
    public const string IS_AUX     = 'AUX'; // Select Input Source AUX
    public const string IS_AUX1    = 'AUX1'; // Select Input Source AUX1
    public const string IS_AUX2    = 'AUX2'; // Select Input Source AUX2
    public const string IS_AUX3    = 'AUX3'; // Select Input Source AUX3
    public const string IS_AUX4    = 'AUX4'; // Select Input Source AUX4
    public const string IS_AUX5    = 'AUX5'; // Select Input Source AUX5
    public const string IS_AUX6    = 'AUX6'; // Select Input Source AUX6
    public const string IS_AUX7    = 'AUX7'; // Select Input Source AUX7
    public const string IS_VAUX  = 'V.AUX'; // Select Input Source V.AUX
    public const string IS_DOCK  = 'DOCK'; // Select Input Source Dock
    public const string IS_IPOD  = 'IPOD'; // Select Input Source iPOD
    public const string IS_USB   = 'USB'; // Select Input Source USB
    public const string IS_AUXA  = 'AUXA'; // Select Input Source AUXA
    public const string IS_AUXB  = 'AUXB'; // Select Input Source AUXB
    public const string IS_AUXC = 'AUXC'; // Select Input Source AUXC
    public const string IS_AUXD = 'AUXD'; // Select Input Source AUXD
    public const string IS_NETUSB = 'NET/USB'; // Select Input Source NET/USB
    public const string IS_NET    = 'NET'; // Select Input Source NET
    public const string IS_LASTFM = 'LASTFM'; // Select Input Source LastFM
    public const string IS_FLICKR = 'FLICKR'; // Select Input Source Flickr
    public const string IS_FAVORITES = 'FAVORITES'; // Select Input Source Favorites
    public const string IS_IRADIO    = 'IRADIO'; // Select Input Source Internet Radio
    public const string IS_SERVER    = 'SERVER'; // Select Input Source Server
    public const string IS_NAPSTER   = 'NAPSTER'; // Select Input Source Napster
    public const string IS_USB_IPOD  = 'USB/IPOD'; // Select Input USB/IPOD
    public const string IS_MXPORT    = 'MXPORT'; // Select Input MXPORT
    public const string IS_SOURCE    = 'SOURCE'; // Select Input Source of Main Zone
    public const string IS_ON        = 'ON'; // Select Input Source On
    public const string IS_OFF       = 'OFF'; // Select Input Source Off

    public static array $SIMapping        = ['CBL/SAT'      => self::IS_SAT_CBL,
                                             'MediaPlayer'  => self::IS_MPLAY,
                                             'Media Player' => self::IS_MPLAY,
                                             'Media Server' => self::IS_SERVER,
                                             'iPod/USB'     => self::IS_USB_IPOD,
                                             'M-XPORT'      => self::IS_MXPORT,
                                             'TVAUDIO'      => self::IS_TV,
                                             'TV AUDIO'     => self::IS_TV,
                                             'Bluetooth'    => self::IS_BT,
                                             'Blu-ray'      => self::IS_BD,
                                             'Online Music' => self::IS_NET,
                                             'NETWORK'                                 => self::IS_NET,
                                             'Internet Radio'                          => self::IS_IRADIO,
                                             'Last. fm'                                => self::IS_LASTFM,
                                             'FM'                                      => self::IS_TUNER,
    ];

    public static array $SI_InputSettings = [
        self::IS_PHONO,
        self::IS_CD,
        self::IS_TUNER,
        self::IS_DVD,
        self::IS_HDP,
        self::IS_BD,
        self::IS_BT,
        self::IS_MPLAY,
        self::IS_TV,
        self::IS_TV_CBL,
        self::IS_SAT_CBL,
        self::IS_SAT,
        self::IS_VCR,
        self::IS_DVR,
        self::IS_GAME,
        self::IS_GAME2,
        self::IS_AUX,
        self::IS_AUX1,
        self::IS_AUX2,
        self::IS_AUX3,
        self::IS_AUX4,
        self::IS_AUX5,
        self::IS_AUX6,
        self::IS_AUX7,
        self::IS_AUXA,
        self::IS_AUXB,
        self::IS_AUXC,
        self::IS_AUXD,
        self::IS_NETUSB,
        self::IS_VAUX,
        self::IS_DOCK,
        self::IS_IPOD,
        self::IS_NETUSB,
        self::IS_NET,
        self::IS_LASTFM,
        self::IS_FLICKR,
        self::IS_FAVORITES,
        self::IS_IRADIO,
        self::IS_SERVER,
        self::IS_NAPSTER,
        self::IS_USB,
        self::IS_USB_IPOD,
        self::IS_MXPORT,
        self::IS_SOURCE,
    ];

    //ZM Mainzone
    public const string ZMOFF = 'OFF'; // Power Off
    public const string ZMON  = 'ON'; // Power On

    //SD
    public const string SDAUTO    = 'AUTO'; // Auto Mode
    public const string SDHDMI    = 'HDMI'; // HDMI Mode
    public const string SDDIGITAL = 'DIGITAL'; // Digital Mode
    public const string SDANALOG  = 'ANALOG'; // Analog Mode
    public const string SDEXTIN   = 'EXT.IN'; // Ext.In Mode
    public const string SD71IN    = '7.1IN'; // 7.1 In Mode
    public const string SDNO      = 'NO'; // no Input
    public const string SDARC     = 'ARC'; // ARC (nur im Event)
    public const string SDEARC    = 'EARC'; // EARC (nur im Event)

    //DC Digital Input
    public const string DCAUTO = 'AUTO'; // Auto Mode
    public const string DCPCM  = 'PCM'; // PCM Mode
    public const string DCDTS  = 'DTS'; // DTS Mode

    //MS Surround Mode
    public const string MSDIRECT       = 'DIRECT'; // Direct Mode
    public const string MSPUREDIRECT   = 'PURE DIRECT'; // Pure Direct Mode
    public const string MSSTEREO       = 'STEREO'; // Stereo Mode
    public const string MSSTANDARD     = 'STANDARD'; // Standard Mode
    public const string MSDOLBYDIGITAL = 'DOLBY DIGITAL'; // Dolby Digital Mode
    public const string MSDTSSURROUND  = 'DTS SURROUND'; // DTS Surround Mode
    public const string MSMCHSTEREO    = 'MCH STEREO'; // Multi Channel Stereo Mode
    public const string MS7CHSTEREO    = '7CH STEREO'; // 7 Channel Stereo Mode
    public const string MSWIDESCREEN   = 'WIDE SCREEN'; // Wide Screen Mode
    public const string MSSUPERSTADIUM = 'SUPER STADIUM'; // Super Stadium Mode
    public const string MSROCKARENA    = 'ROCK ARENA'; // Rock Arena Mode
    public const string MSJAZZCLUB     = 'JAZZ CLUB'; // Jazz Club Mode
    public const string MSCLASSICCONCERT = 'CLASSIC CONCERT'; // Classic Concert Mode
    public const string MSMONOMOVIE      = 'MONO MOVIE'; // Mono Movie Mode
    public const string MSMATRIX         = 'MATRIX'; // Matrix Mode
    public const string MSVIDEOGAME      = 'VIDEO GAME'; // Video Game Mode
    public const string MSVIRTUAL        = 'VIRTUAL'; // Virtual Mode
    public const string MSMOVIE          = 'MOVIE'; // Movie
    public const string MSMUSIC          = 'MUSIC'; // Music
    public const string MSGAME           = 'GAME'; // Game
    public const string MSAUTO           = 'AUTO'; // Auto
    public const string MSNEURAL         = 'NEURAL'; // Neural
    public const string MSAURO3D         = 'AURO3D'; //Auro 3D
 //   public const AURO3D = 'AURO3D'; //Auro 3D
    public const string MSAURO2DSURR = 'AURO2DSURR'; //Auro 2D

    public const string MSLEFT  = 'LEFT'; // Change to previous Surround Mode
    public const string MSRIGHT = 'RIGHT'; // Change to next Surround Mode
    //Quick Select Mode
    public const string MSQUICK0 = '0'; // Quick Select 0 Mode Select
    public const string MSQUICK1 = '1'; // Quick Select 1 Mode Select
    public const string MSQUICK2 = '2'; // Quick Select 2 Mode Select
    public const string MSQUICK3 = '3'; // Quick Select 3 Mode Select
    public const string MSQUICK4 = '4'; // Quick Select 4 Mode Select
    public const string MSQUICK5 = '5'; // Quick Select 5 Mode Select
    public const string MSQUICK6 = '6'; // Quick Select 6 Mode Select (ab CY2026)

    //MSQUICKMEMORY
    public const string MSQUICK1MEMORY = '1 MEMORY'; // Quick Select 1 Mode Memory
    public const string MSQUICK2MEMORY = '2 MEMORY'; // Quick Select 2 Mode Memory
    public const string MSQUICK3MEMORY = '3 MEMORY'; // Quick Select 3 Mode Memory
    public const string MSQUICK4MEMORY = '4 MEMORY'; // Quick Select 4 Mode Memory
    public const string MSQUICK5MEMORY = '5 MEMORY'; // Quick Select 5 Mode Memory
    public const string MSQUICK6MEMORY = '6 MEMORY'; // Quick Select 6 Mode Memory
    public const string MSQUICKSTATE   = 'QUICK ?'; // QUICK ? Return MSQUICK Status

    //Smart Select Mode
    public const string MSSMART0 = '0'; // Smart Select 0 Mode Select
    public const string MSSMART1 = '1'; // Smart Select 1 Mode Select
    public const string MSSMART2 = '2'; // Smart Select 2 Mode Select
    public const string MSSMART3 = '3'; // Smart Select 3 Mode Select
    public const string MSSMART4 = '4'; // Smart Select 4 Mode Select
    public const string MSSMART5 = '5'; // Smart Select 5 Mode Select

    //VS
    //VSMONI Set HDMI Monitor
    public const string VSMONIAUTO = 'AUTO'; // 1
    public const string VSMONI1    = '1'; // 1
    public const string VSMONI2    = '2'; // 2

    //VSASP
    public const string ASPNRM = 'NRM'; // Set Normal Mode
    public const string ASPFUL = 'FUL'; // Set Full Mode
    public const string ASP    = ' ?'; // ASP? Return VSASP Status

    //VSSC Set Resolution
    public const string SC48P   = '48P'; // Set Resolution to 480p/576p
    public const string SC10I   = '10I'; // Set Resolution to 1080i
    public const string SC72P   = '72P'; // Set Resolution to 720p
    public const string SC10P   = '10P'; // Set Resolution to 1080p
    public const string SC10P24 = '10P24'; // Set Resolution to 1080p:24Hz
    public const string SC4K    = '4K'; // Set Resolution to 4K
    public const string SC4KF   = '4KF'; // Set Resolution to 4K (60/50)
    public const string SC8K    = '8K'; // Set Resolution to 8K
    public const string SCAUTO  = 'AUTO'; // Set Resolution to Auto
    public const string SC      = ' ?'; // SC? Return VSSC Status

    //VSSCH Set Resolution HDMI
    public const string SCH48P   = '48P'; // Set Resolution to 480p/576p HDMI
    public const string SCH10I   = '10I'; // Set Resolution to 1080i HDMI
    public const string SCH72P   = '72P'; // Set Resolution to 720p HDMI
    public const string SCH10P   = '10P'; // Set Resolution to 1080p HDMI
    public const string SCH10P24 = '10P24'; // Set Resolution to 1080p:24Hz HDMI
    public const string SCH4K    = '4K'; // Set Resolution to 4K
    public const string SCH4KF   = '4KF'; // Set Resolution to 4K (60/50)
    public const string SCH8K    = '8K'; // Set Resolution to 8K
    public const string SCHAUTO  = 'AUTO'; // Set HDMI Upcaler to Auto
    public const string SCHOFF   = 'OFF'; // Set HDMI Upscale to Off
    public const string SCH      = ' ?'; // SCH? Return VSSCH Status(HDMI)

    //VSAUDIO Set HDMI Audio Output
    public const string AUDIOAMP = ' AMP'; // Set HDMI Audio Output to AMP
    public const string AUDIOTV  = ' TV'; // Set HDMI Audio Output to TV
    public const string AUDIO    = ' ?'; // AUDIO? Return VSAUDIO Status

    //VSVPM Set Video Processing Mode
    public const string VPMAUTO = 'AUTO'; // Set Video Processing Mode to Auto
    public const string VPGAME  = 'GAME'; // Set Video Processing Mode to Game
    public const string VPMOVI  = 'MOVI'; // Set Video Processing Mode to Movie
    public const string VPMBYP  = 'MBYP'; // Set Video Processing Mode to Bypass
    public const string VPM     = ' ?'; // VPM? Return VSVPM Status

    //VSVST Set Vertical Stretch
    public const string VSTON  = ' ON'; // Set Vertical Stretch On
    public const string VSTOFF = ' OFF'; // Set Vertical Stretch Off
    public const string VST    = ' ?'; // VST? Return VSVST Status

    //PS Parameter
    //PSTONE Tone Control
    public const string TONECTRL        = 'PSTONE CTRL'; // Tone Control On
    public const string PSTONECTRLON    = ' ON'; // Tone Control On
    public const string PSTONECTRLOFF   = ' OFF'; // Tone Control Off
    public const string PSTONECTRLSTATE = ' ?'; // TONE CTRL ? Return PSTONE CONTROL Status

    //PSSB Surround Back SP Mode
    public const string SBMTRXON     = ':MTRX ON'; // Surround Back SP Mode Matrix
    public const string SBPL2XCINEMA = ':PL2X CINEMA'; // Surround Back SP Mode	PL2X Cinema
    public const string SBPL2XMUSIC  = ':PL2X MUSIC'; // Surround Back SP Mode	PL2X Music
    public const string SBON         = ':ON'; // Surround Back SP Mode on
    public const string SBOFF        = ':OFF'; // Surround Back SP Mode off

    //PSCINEMAEQ Cinema EQ
    public const string CINEMAEQCOMMAND = 'PSCINEMA EQ'; // Cinema EQ
    public const string CINEMAEQON      = '.ON'; // Cinema EQ on
    public const string CINEMAEQOFF     = '.OFF'; // Cinema EQ off
    public const string CINEMAEQ        = '. ?'; // Return PSCINEMA EQ.Status

    //PSHTEQ HT EQ
    public const string HTEQCOMMAND = 'PSHTEQ'; // HT EQ
    public const string HTEQON      = ' ON'; // HT EQ on
    public const string HTEQOFF     = ' OFF'; // HT EQ off
    public const string HTEQ        = ' ?'; // Return HT EQ.Status

    //PSMODE Mode Music
    public const string MODEMUSIC    = ':MUSIC'; // Mode Music CINEMA / MUSIC / GAME / PL mode change
    public const string MODECINEMA   = ':CINEMA'; // This parameter can change DOLBY PL2,PL2x,NEO:6 mode.
    public const string MODEGAME     = ':GAME'; // SB=ON：PL2x mode / SB=OFF：PL2 mode GAME can change DOLBY PL2 & PL2x mode PSMODE:PRO LOGIC
    public const string MODEPROLOGIC = ':PRO LOGIC'; // PL can change ONLY DOLBY PL2 mode
    public const string MODESTATE    = ': ?'; // Return PSMODE: Status

    //PSDOLVOL Dolby Volume direct change
    public const string DOLVOLON  = ' ON'; // Dolby Volume direct change on
    public const string DOLVOLOFF = ' OFF'; // Dolby Volume direct change off
    public const string DOLVOL    = ': ?'; // Return PSDOLVOL Status

    //PSVOLLEV Dolby Volume Leveler direct change
    public const string VOLLEVLOW = ' LOW'; // Dolby Volume Leveler direct change Low
    public const string VOLLEVMID = ' MID'; // Dolby Volume Leveler direct change Middle
    public const string VOLLEVHI  = ' HI'; // Dolby Volume Leveler direct change High
    public const string VOLLEV    = ': ?'; // Return PSVOLLEV Status

    // PSVOLMOD Dolby Volume Modeler direct change
    public const string VOLMODHLF = ' HLF'; // Dolby Volume Modeler direct change half
    public const string VOLMODFUL = ' FUL'; // Dolby Volume Modeler direct change full
    public const string VOLMODOFF = ' OFF'; // Dolby Volume Modeler direct change off
    public const string VOLMOD    = ': ?'; // Return PSVOLMOD Status

    //PSFH Front Height
    public const string PSFHON    = ':ON'; // FRONT HEIGHT ON
    public const string PSFHOFF   = ':OFF'; // FRONT HEIGHT OFF
    public const string PSFHSTATE = ': ?'; // Return PSFH: Status

    //PSPHG PL2z Height Gain direct change
    public const string PHGLOW   = ' LOW'; // PL2z HEIGHT GAIN direct change low
    public const string PHGMID   = ' MID'; // PL2z HEIGHT GAIN direct change middle
    public const string PHGHI    = ' HI'; // PL2z HEIGHT GAIN direct change high
    public const string PHGSTATE = ' ?'; // Return PSPHG Status

    //PSSP Speaker Output set
    public const string SPFH    = ':FH'; // Speaker Output set FH
    public const string SPFW    = ':FW'; // Speaker Output set FW
    public const string SPSB    = ':SB'; // Speaker Output set SB
    public const string SPHW    = ':HW'; // Speaker Output set HW
    public const string SPBH    = ':BH'; // Speaker Output set BH
    public const string SPBW    = ':BW'; // Speaker Output set BW
    public const string SPFL    = ':FL'; // Speaker Output set FL
    public const string SPHF    = ':HF'; // Speaker Output set HF
    public const string SPFR    = ':FR'; // Speaker Output set FR
    public const string SPOFF   = ':OFF'; // Speaker Output set off
    public const string SPSTATE = ' ?'; // Return PSSP: Status

    // MulEQ XT 32 mode direct change
    public const string MULTEQAUDYSSEY = ':AUDYSSEY'; // MultEQ XT 32 mode direct change MULTEQ:AUDYSSEY
    public const string MULTEQBYPLR    = ':BYP.LR'; // MultEQ XT 32 mode direct change MULTEQ:BYP.LR
    public const string MULTEQFLAT     = ':FLAT'; // MultEQ XT 32 mode direct change MULTEQ:FLAT
    public const string MULTEQMANUAL   = ':MANUAL'; // MultEQ XT 32 mode direct change MULTEQ:MANUAL
    public const string MULTEQOFF      = ':OFF'; // MultEQ XT 32 mode direct change MULTEQ:OFF
    public const string MULTEQ         = ': ?'; // Return PSMULTEQ: Status

    //PSDYNEQ Dynamic EQ
    public const string DYNEQON  = ' ON'; // Dynamic EQ = ON
    public const string DYNEQOFF = ' OFF'; // Dynamic EQ = OFF
    public const string DYNEQ    = ' ?'; // Return PSDYNEQ Status

    //PSLFC Audyssey LFC
    public const string LFCON  = ' ON'; // Audyssey LFC = ON
    public const string LFCOFF = ' OFF'; // Audyssey LFC = OFF
    public const string LFC    = ' ?'; // Return Audyssey LFC Status

    //PSGEQ Graphic EQ
    public const string GEQON  = ' ON'; // Graphic EQ = ON
    public const string GEQOFF = ' OFF'; // Graphic EQ = OFF
    public const string GEQ    = ' ?'; // Return Graphic EQ Status

    //PSREFLEV Reference Level Offset
    public const string REFLEV0  = ' 0'; // Reference Level Offset=0dB
    public const string REFLEV5  = ' 5'; // Reference Level Offset=5dB
    public const string REFLEV10 = ' 10'; // Reference Level Offset=10dB
    public const string REFLEV15 = ' 15'; // Reference Level Offset=15dB
    public const string REFLEV   = ' ?'; // Return PSREFLEV Status

    //PSREFLEV Reference Level Offset
    public const string DIRAC1   = ' 1'; // Filter Slot 1
    public const string DIRAC2   = ' 2'; // Filter Slot 2
    public const string DIRAC3   = ' 3'; // Filter Slot 3
    public const string DIRACOFF = ' OFF'; // Filter Off


    //PSDYNVOL (old version)
    public const string DYNVOLNGT = ' NGT'; // Dynamic Volume = Midnight
    public const string DYNVOLEVE = ' EVE'; // Dynamic Volume = Evening
    public const string DYNVOLDAY = ' DAY'; // Dynamic Volume = Day
    public const string DYNVOL    = ' ?'; // Return PSDYNVOL Status
    //PSDYNVOL
    public const string DYNVOLHEV = ' HEV'; // Dynamic Volume = Heavy
    public const string DYNVOLMED = ' MED'; // Dynamic Volume = Medium
    public const string DYNVOLLIT = ' LIT'; // Dynamic Volume = Light
    public const string DYNVOLOFF = ' OFF'; // Dynamic Volume = Off
    public const string DYNVOLON  = ' ON'; // Dynamic Volume = Off

    //PSDSX Audyssey DSX ON
    public const string PSDSXONHW   = ' ONHW'; // Audyssey DSX ON(Height/Wide)
    public const string PSDSXONH    = ' ONH'; // Audyssey DSX ON(Height)
    public const string PSDSXONW    = ' ONW'; // Audyssey DSX ON(Wide)
    public const string PSDSXOFF    = ' OFF'; // Audyssey DSX OFF
    public const string PSDSXSTATUS = ' ?'; // Return PSDSX Status

    //PSSTW Stage Width
    public const string STWUP   = ' UP'; // STAGE WIDTH UP
    public const string STWDOWN = ' DOWN'; // STAGE WIDTH DOWN
    public const string STW     = ' '; // STAGE WIDTH ** ---AVR-4311 can be operated from -10 to +10

    //PSSTH Stage Height
    public const string STHUP   = ' UP'; // STAGE HEIGHT UP
    public const string STHDOWN = ' DOWN'; // STAGE HEIGHT DOWN
    public const string STH     = ' '; // STAGE HEIGHT ** ---AVR-4311 can be operated from -10 to +10

    //PSBAS Bass
    public const string BASUP   = ' UP'; // BASS UP
    public const string BASDOWN = ' DOWN'; // BASS DOWN
    public const string BAS     = ' '; // BASS ** ---AVR-4311 can be operated from -6 to +6

    //PSTRE Treble
    public const string TREUP   = ' UP'; // TREBLE UP
    public const string TREDOWN = ' DOWN'; // TREBLE DOWN
    public const string TRE     = ' '; // TREBLE ** ---AVR-4311 can be operated from -6 to +6

    //PSDRC DRC direct change
    public const string DRCAUTO = ' AUTO'; // DRC direct change
    public const string DRCLOW  = ' LOW'; // DRC Low
    public const string DRCMID  = ' MID'; // DRC Middle
    public const string DRCHI   = ' HI'; // DRC High
    public const string DRCOFF  = ' OFF'; // DRC off
    public const string DRC     = ' ?'; // Return PSDRC Status

    //PSMDAX MDAX direct change
    public const string MDAXLOW = ' LOW'; // DRC Low
    public const string MDAXMID = ' MID'; // DRC Middle
    public const string MDAXHI  = ' HI'; // DRC High
    public const string MDAXOFF = ' OFF'; // DRC off
    public const string MDAX    = ' ?'; // Return PSDRC Status

    //PSDCO D.Comp direct change
    public const string DCOOFF  = ' OFF'; // D.COMP direct change
    public const string DCOLOW  = ' LOW'; // D.COMP Low
    public const string DCOMID  = ' MID'; // D.COMP Middle
    public const string DCOHIGH = ' HIGH'; // D.COMP High
    public const string DCO     = ' ?'; // Return PSDCO Status

    //PSLFE LFE
    public const string LFEDOWN = ' DOWN'; // LFE DOWN
    public const string LFEUP   = ' UP'; // LFE UP
    public const string LFE     = ' '; // LFE ** ---AVR-4311 can be operated from 0 to -10

    //PSEFF Effect direct change
    public const string PSEFFON  = ' ON'; // EFFECT ON direct change
    public const string PSEFFOFF = ' OFF'; // EFFECT OFF direct change

    public const string PSEFFUP     = ' UP'; // EFFECT UP direct change
    public const string PSEFFDOWN   = ' DOWN'; // EFFECT DOWN direct change
    public const string PSEFFSTATUS = ' ?'; // EFFECT ** ---AVR-4311 can be operated from 1 to 15

    //PSDELAY Delay
    public const string PSDELAYUP   = ' UP'; // DELAY UP
    public const string PSDELAYDOWN = ' DOWN'; // DELAY DOWN
    public const string PSDELAYVAL  = ' '; // DELAY ** ---AVR-4311 can be operated from 0 to 300

    //PSAFD Auto Flag Detection Mode
    public const string AFDON  = ' ON'; // AFDM ON
    public const string AFDOFF = ' OFF'; // AFDM OFF
    public const string AFD    = ' '; // Return PSAFD Status

    //PSPAN Panorama
    public const string PANON  = ' ON'; // PANORAMA ON
    public const string PANOFF = ' OFF'; // PANORAMA OFF
    public const string PAN    = ' ?'; // Return PSPAN Status

    //PSDIM Dimension
    public const string PSDIMUP   = ' UP'; // DIMENSION UP
    public const string PSDIMDOWN = ' DOWN'; // DIMENSION DOWN
    public const string PSDIMSET  = ' '; // ---AVR-4311 can be operated from 0 to 6

    //PSCEN Center Width
    public const string CENUP   = 'CEN UP'; // CENTER WIDTH UP
    public const string CENDOWN = 'CEN DOWN'; // CENTER WIDTH DOWN
    public const string CEN     = 'CEN '; // ---AVR-4311 can be operated from 0 to 7

    //PSCEI Center Image
    public const string CEIUP   = 'CEI UP'; // CENTER IMAGE UP
    public const string CEIDOWN = 'CEI DOWN'; // CENTER IMAGE DOWN
    public const string CEI     = 'CEI '; // ---AVR-4311 can be operated from 0 to 7

    //PSRSZ Room Size
    public const string RSZN = ' N';
    public const string RSZS = ' S';
    public const string RSZMS = ' MS';
    public const string RSZM  = ' M';
    public const string RSZML = ' ML';
    public const string RSZL  = ' L';

    //PSSW ATT
    public const string ATTON  = 'ATT ON'; // SW ATT ON
    public const string ATTOFF = 'ATT OFF'; // SW ATT OFF
    public const string ATT    = 'ATT ?'; // Return PSATT Status

    //PSSWR
    public const string PSSWRON  = ' ON'; // SW ATT ON
    public const string PSSWROFF = ' OFF'; // SW ATT OFF
    public const string SWR      = ' ?'; // Return PSATT Status

    //PSLOM
    public const string PSLOMON  = ' ON'; // SW ATT ON
    public const string PSLOMOFF = ' OFF'; // SW ATT OFF
    public const string LOM      = ' ?'; // Return PSATT Status

    //Audio Restorer - neue Kommandos bei neueren(?) Modellen
    public const string PSRSTROFF = ' OFF'; //Audio Restorer Off
    //public const PSRSTRMODE1 = ' MODE1'; //Audio Restorer 64
    //public const PSRSTRMODE2 = ' MODE2'; //Audio Restorer 96
    //public const PSRSTRMODE3 = ' MODE3'; //Audio Restorer HQ
    public const string PSRSTRMODE1 = ' HI'; //Audio Restorer 64
    public const string PSRSTRMODE2 = ' MID'; //Audio Restorer 96
    public const string PSRSTRMODE3 = ' LOW'; //Audio Restorer HQ

    //Front Speaker
    public const string PSFRONTSPA  = ' SPA'; //Speaker A
    public const string PSFRONTSPB  = ' SPB'; //Speaker B
    public const string PSFRONTSPAB = ' A+B'; //Speaker A+B

    //Cursor Menu
    public const string MNCUP = 'CUP'; // Cursor Up
    public const string MNCDN = 'CDN'; // Cursor Down
    public const string MNCRT = 'CRT'; // Cursor Right
    public const string MNCLT = 'CLT'; // Cursor Left
    public const string MNENT = 'ENT'; // Cursor Enter
    public const string MNRTN = 'RTN'; // Cursor Return

    //GUI Menu (Setup Menu)
    public const string MNMEN    = 'MNMEN'; // GUI Menu
    public const string MNMENON  = ' ON'; // GUI Menu On
    public const string MNMENOFF = ' OFF'; // GUI Menu Off

    //GUI Source Select Menu
    public const string MNSRC    = 'MNSRC'; // Source Select Menu
    public const string MNSRCON  = ' ON'; // Source Select Menu On
    public const string MNSRCOFF = ' OFF'; // Source Select Menu Off

    // Surround Modes Response

    // Surround Modes Varmapping

    //Dolby Digital
    public const string DOLBYPROLOGIC = 'DOLBY PRO LOGIC'; // DOLBY PRO LOGIC
    public const string DOLBYPL2C     = 'DOLBY PL2 C'; // DOLBY PL2 C
    public const string DOLBYPL2M     = 'DOLBY PL2 M'; // DOLBY PL2 M
    public const string DOLBYPL2G     = 'DOLBY PL2 G'; // DOLBY PL2 G
    public const string DOLBYPLIIMV   = 'DOLBY PLII MV';
    public const string DOLBYPLIIMS   = 'DOLBY PLII MS';
    public const string DOLBYPLIIGM   = 'DOLBY PLII GM';
    public const string DOLBYPL2XC    = 'DOLBY PL2X C'; // DOLBY PL2X C
    public const string DOLBYPL2XM    = 'DOLBY PL2X M'; // DOLBY PL2X M
    public const string DOLBYPL2XG    = 'DOLBY PL2X G'; // DOLBY PL2X G
    public const string DOLBYPL2ZH    = 'DOLBY PL2Z H'; // DOLBY PL2Z H
    public const string DOLBYPL2XH  = 'DOLBY PL2X H'; // DOLBY PL2X H
    public const string DOLBYDEX    = 'DOLBY D EX'; // DOLBY D EX
    public const string DOLBYDPL2XC = 'DOLBY D+PL2X C';
    public const string DOLBYDPL2XM      = 'DOLBY D+PL2X M';
    public const string DOLBYDPL2ZH      = 'DOLBY D+PL2Z H';
    public const string DOLBYAUDIODDDSUR = 'DOLBY AUDIO-DD+DSUR';
    public const string PLDSX            = 'PL DSX'; // PL DSX
    public const string PL2CDSX          = 'PL2 C DSX'; // PL2 C DSX
    public const string PL2MDSX          = 'PL2 M DSX'; // PL2 M DSX
    public const string PL2GDSX          = 'PL2 G DSX'; // PL2 G DSX
    public const string PL2XCDSX         = 'PL2X C DSX'; // PL2X C DSX
    public const string PL2XMDSX         = 'PL2X M DSX'; // PL2X M DSX
    public const string PL2XGDSX        = 'PL2X G DSX'; // PL2X G DSX
    public const string DOLBYDPLUSPL2XC = 'DOLBY D+ +PL2X C'; // DOLBY D+ +PL2X C
    public const string DOLBYDPLUSPL2XM = 'DOLBY D+ +PL2X M'; // DOLBY D+ +PL2X M
    public const string DOLBYDPLUSPL2XH = 'DOLBY D+ +PL2X H'; // DOLBY D+ +PL2X H
    public const string DOLBYHDPL2XC    = 'DOLBY HD+PL2X C'; // DOLBY HD+PL2X C
    public const string DOLBYHDPL2XM    = 'DOLBY HD+PL2X M'; // DOLBY HD+PL2X M
    public const string DOLBYHDPL2XH    = 'DOLBY HD+PL2X H'; // DOLBY HD+PL2X H
    public const string MULTICNIN       = 'MULTI CH IN'; // MULTI CH IN
    public const string MCHINPL2XC      = 'M CH IN+PL2X C'; // M CH IN+PL2X C
    public const string MCHINPL2XM      = 'M CH IN+PL2X M'; // M CH IN+PL2X M
    public const string MCHINPL2ZH      = 'M CH IN+PL2Z H';
    public const string MCHINDSUR       = 'M CH IN+DSUR';
    public const string MCHINNEURALX    = 'M CH IN+NEURAL:X'; // M CH IN+NEURAL:X

    public const string DOLBYDPLUS   = 'DOLBY D+'; // DOLBY D+
    public const string DOLBYDPLUSEX = 'DOLBY D+ +EX'; // DOLBY D+ +EX
    public const string DOLBYTRUEHD  = 'DOLBY TRUEHD'; // DOLBY TRUEHD
    public const string DOLBYHD      = 'DOLBY HD'; // DOLBY HD
    public const string DOLBYHDEX    = 'DOLBY HD+EX'; // DOLBY HD+EX
    public const string DOLBYPL2H    = 'DOLBY PL2 H'; // MSDOLBY PL2 H

    public const string DOLBYSURROUND  = 'DOLBY SURROUND'; // MSDOLBY SURROUND
    public const string DOLBYAUDIODSUR = 'DOLBY AUDIO-DSUR';
    public const string DOLBYATMOS     = 'DOLBY ATMOS'; // MSDOLBY ATMOS
    public const string DOLBYAUDIODD   = 'DOLBY AUDIO-DD';
    public const string DOLBYDIGITAL   = 'DOLBY DIGITAL'; // MSDOLBY DIGITAL
    public const string DOLBYDDS       = 'DOLBY D+DS'; // MSDOLBY D+DS
    public const string MPEG2AAC       = 'MPEG2 AAC'; // MSMPEG2 AAC
    public const string MPEG4AAC       = 'MPEG4 AAC'; // MSMPEG4 AAC
    public const string MPEGH          = 'MPEG-H'; // MSMPEG4 AAC
    public const string AACDOLBYEX     = 'AAC+DOLBY EX'; // MSAAC+DOLBY EX
    public const string AACPL2XC       = 'AAC+PL2X C'; // MSAAC+PL2X C
    public const string AACPL2XM     = 'AAC+PL2X M'; // MSAAC+PL2X M
    public const string AACPL2ZH     = 'AAC+PL2Z H';
    public const string AACDSUR      = 'AAC+DSUR';
    public const string AACDS        = 'AAC+DS'; // MSAAC+DS
    public const string AACNEOXC   = 'AAC+NEO:X C'; // MSAAC+NEO:X C
    public const string AACNEOXM   = 'AAC+NEO:X M'; // MSAAC+NEO:X M
    public const string AACNEOXG   = 'AAC+NEO:X G'; // MSAAC+NEO:X G

    //DTS Surround
    public const string DTSNEO6C     = 'DTS NEO:6 C'; // DTS NEO:6 C
    public const string DTSNEO6M     = 'DTS NEO:6 M'; // DTS NEO:6 M
    public const string DTSNEOXC     = 'DTS NEO:X C'; // DTS NEO:X C
    public const string DTSNEOXM     = 'DTS NEO:X M'; // DTS NEO:X M
    public const string DTSNEOXG     = 'DTS NEO:X G'; // DTS NEO:X G
    public const string NEURALX      = 'NEURAL:X'; // NEURAL:X
    public const string VIRTUALX     = 'VIRTUAL:X'; // VIRTUAL:X
    public const string DTSESDSCRT61 = 'DTS ES DSCRT6.1'; // DTS ES DSCRT6.1
    public const string DTSESMTRX61  = 'DTS ES MTRX6.1'; // DTS ES MTRX6.1
    public const string DTSPL2XC     = 'DTS+PL2X C'; // DTS+PL2X C
    public const string DTSPL2XM     = 'DTS+PL2X M'; // DTS+PL2X M
    public const string DTSPL2ZH     = 'DTS+PL2Z H'; // DTS+PL2Z H
    public const string DTSDSUR      = 'DTS+DSUR';
    public const string DTSDS        = 'DTS+DS'; // DTS+DS
    public const string DTSPLUSNEO6  = 'DTS+NEO:6'; // DTS+NEO:6
    public const string DTSPLUSNEOXC = 'DTS+NEO:X C'; // DTS PLUS NEO:X C
    public const string DTSPLUSNEOXM = 'DTS+NEO:X M'; // DTS PLUS NEO:X M
    public const string DTSPLUSNEOXG = 'DTS+NEO:X G'; // DTS PLUS NEO:X G
    public const string DTSPLUSNEURALX = 'DTS+NEURAL:X'; // DTS+NEURAL:X
    public const string DTS9624        = 'DTS96/24'; // DTS96/24
    public const string DTS96ESMTRX    = 'DTS96 ES MTRX'; // DTS96 ES MTRX
    public const string DTSHDPL2XC     = 'DTS HD+PL2X C'; // DTS HD+PL2X C
    public const string DTSHDPL2XM     = 'DTS HD+PL2X M'; // DTS HD+PL2X M
    public const string DTSHDPL2ZH     = 'DTS HD+PL2Z H'; // DTS HD+PL2Z H
    public const string DTSHDDSUR      = 'DTS HD+DSUR';
    public const string DTSHDDS        = 'DTS HD+DS'; // DTS HD+DS
    public const string NEO6CDSX       = 'NEO:6 C DSX'; // NEO:6 C DSX
    public const string NEO6MDSX       = 'NEO:6 M DSX'; // NEO:6 M DSX
    public const string DTSHD          = 'DTS HD'; // DTS HD
    public const string DTSHDMSTR   = 'DTS HD MSTR'; // DTS HD MSTR
    public const string DTSHDNEO6   = 'DTS HD+NEO:6'; // DTS HD+NEO:6
    public const string DTSES8CHDSCRT = 'DTS ES 8CH DSCRT'; // DTS ES 8CH DSCRT
    public const string DTSEXPRESS    = 'DTS EXPRESS'; // DTS EXPRESS
    public const string DOLBYDNEOXC   = 'DOLBY D+NEO:X C'; // MSDOLBY D+NEO:X C
    public const string DOLBYDNEOXM   = 'DOLBY D+NEO:X M'; // MSDOLBY D+NEO:X M
    public const string DOLBYDNEOXG   = 'DOLBY D+NEO:X G'; // MSDOLBY D+NEO:X G
    public const string DOLBYAUDIODDPLUSNEURALX = 'DOLBY AUDIO-DD+NEURAL:X';
    public const string DOLBYAUDIODDPLUS        = 'DOLBY AUDIO-DD+';
    public const string DOLBYDNEURALX           = 'DOLBY D+NEURAL:X'; // MSDOLBY D+NEURAL:X
    public const string MCHINDS                 = 'M CH IN+DS'; // MSM CH IN+DS
    public const string MCHINNEOXC              = 'M CH IN+NEO:X C'; // MSM CH IN+NEO:X C
    public const string MCHINNEOXM              = 'M CH IN+NEO:X M'; // MSM CH IN+NEO:X M
    public const string MCHINNEOXG              = 'M CH IN+NEO:X G'; // MSM CH IN+NEO:G C
    public const string DOLBYDPLUSDS            = 'DOLBY D+ +DS'; // MSDOLBY D+ +DS
    public const string DOLBYAUDIODDPLUSDSUR    = 'DOLBY AUDIO-DD+ +DSUR';
    public const string DOLBYDPLUSNEOXC         = 'DOLBY D+ +NEO:X C'; // MSDOLBY D+ +NEO:X C
    public const string DOLBYDPLUSNEOXM             = 'DOLBY D+ +NEO:X M'; // MSDOLBY D+ +NEO:X M
    public const string DOLBYDPLUSNEOXG             = 'DOLBY D+ +NEO:X G'; // MSDOLBY D+ +NEO:X G
    public const string DOLBYAUDIODDPLUSPLUSNEURALX = 'DOLBY AUDIO-DD+ +NEURAL:X';
    public const string DOLBYAUDIOTRUEHD            = 'DOLBY AUDIO-TRUEHD';
    public const string DOLBYDPLUSNEURALX           = 'DOLBY D+ +NEURAL:X'; // MSDOLBY D+ +NEURAL:X
    public const string DOLBYHDDS                   = 'DOLBY HD+DS'; // MSDOLBY HD+DS
    public const string DOLBYAUDIOTRUEHDDSUR        = 'DOLBY AUDIO-TRUEHD+DSUR';
    public const string DOLBYAUDIOTRUEHDNEURALX     = 'DOLBY AUDIO-TRUEHD+NEURAL:X';
    public const string DOLBYHDNEOXC                = 'DOLBY HD+NEO:X C'; // MSDOLBY HD+NEO:X C
    public const string DOLBYHDNEOXM                = 'DOLBY HD+NEO:X M'; // MSDOLBY HD+NEO:X M
    public const string DOLBYHDNEOXG                = 'DOLBY HD+NEO:X G'; // MSDOLBY HD+NEO:X G
    public const string DOLBYHDNEURALX              = 'DOLBY HD+NEURAL:X'; // MSDOLBY HD+NEURAL:X
    public const string DTSHDNEOXC              = 'DTS HD+NEO:X C'; // MSDTS HD+NEO:X C
    public const string DTSHDNEOXM              = 'DTS HD+NEO:X M'; // MSDTS HD+NEO:X M
    public const string DTSHDNEOXG              = 'DTS HD+NEO:X G'; // MSDTS HD+NEO:X G

    public const string DSDDIRECT     = 'DSD DIRECT'; // DSD DIRECT
    public const string DSDPUREDIRECT = 'DSD PURE DIRECT'; // DSD PURE DIRECT

    public const string MCHINDOLBYEX = 'M CH IN+DOLBY EX'; // M CH IN+DOLBY EX
    public const string MULTICHIN71  = 'MULTI CH IN 7.1'; // MULTI CH IN 7.1

    public const string AUDYSSEYDSX = 'AUDYSSEY DSX'; // AUDYSSEY DSX

    public const string SURROUNDDISPLAY = 'SurroundDisplay'; // Nur DisplayIdent
    public const string SYSMI           = 'SYSMI'; // Nur DisplayIdent
    public const string SYSDA           = 'SYSDA'; // Nur DisplayIdent
    public const string SSINFAISFSV     = 'SSINFAISFSV'; // Nur DisplayIdent
    public const string SSINFAISSIG     = 'SSINFAISSIG'; // Nur DisplayIdent

    public const string BTTXON  = ' ON';
    public const string BTTXOFF = ' OFF';
    public const string BTTXSP  = ' SP';
    public const string BTTXBT = ' BT';

    public const string SPPR_1 = ' 1';
    public const string SPPR_2 = ' 2';

    // All Zone Stereo
    public const string MNZST   = 'MNZST';
    public const string MNZSTON = ' ON';
    public const string MNZSTOFF = ' OFF';

    public const string PSGEQ    = 'PSGEQ'; // Graphic EQ
    public const string PSGEQON  = ' ON'; // Graphic EQ On
    public const string PSGEQOFF = ' OFF'; // Graphic EQ Off

    public const string PSHEQ    = 'PSHEQ'; // Headphone EQ
    public const string PSHEQON  = ' ON'; // Headphone EQ On
    public const string PSHEQOFF = ' OFF'; // Headphone EQ Off

    public const string PSSWL    = 'PSSWL'; // Subwoofer Level
    public const string PSSWL2   = 'PSSWL2'; // Subwoofer2 Level
    public const string PSSWL3   = 'PSSWL3'; // Subwoofer3 Level
    public const string PSSWL4   = 'PSSWL4'; // Subwoofer4 Level
    public const string PSSWLON  = ' ON'; // Subwoofer Level On
    public const string PSSWLOFF = ' OFF'; // Subwoofer Level Off

    public const string PSDIL    = 'PSDIL'; // Dialog Level Adjust
    public const string PSDILON  = ' ON'; // Dialog Level Adjust On
    public const string PSDILOFF = ' OFF'; // Dialog Level Adjust Off

    public const string STBY      = 'STBY'; // Mainzone Auto Standby
    public const string STBY15M   = '15M'; // Mainzone Auto Standby 15 Minuten
    public const string STBY30M   = '30M'; // Mainzone Auto Standby 30 Minuten
    public const string STBY60M   = '60M'; // Mainzone Auto Standby 60 Minuten
    public const string STBYOFF   = 'OFF'; // Mainzone Auto Standby Off
    public const string Z2STBY    = 'Z2STBY'; // Zone 2 Auto Standby
    public const string Z2STBY2H  = '2H'; // Zone 2 Auto Standby 2h
    public const string Z2STBY4H  = '4H'; // Zone 2 Auto Standby 4h
    public const string Z2STBY8H  = '8H'; // Zone 2 Auto Standby 8h
    public const string Z2STBYOFF = 'OFF'; // Zone 2 Auto Standby Off
    public const string Z3STBY    = 'Z3STBY'; // Zone 3 Auto Standby
    public const string Z3STBY2H  = '2H'; // Zone 3 Auto Standby 2H
    public const string Z3STBY4H  = '4H'; // Zone 3 Auto Standby 4h
    public const string Z3STBY8H  = '8H'; // Zone 3 Auto Standby 8h
    public const string Z3STBYOFF = 'OFF'; // Zone 3 Auto Standby Off
    public const string ECO       = 'ECO'; // ECO Mode
    public const string ECOON     = 'ON'; // ECO Mode On
    public const string ECOAUTO   = 'AUTO'; // ECO Mode Auto
    public const string ECOOFF    = 'OFF'; // ECO Mode Off
    public const string DIM       = 'DIM'; // Dimmer
    public const string DIMBRI    = ' BRI'; // Bright
    public const string DIMDIM    = ' DIM'; // DIM
    public const string DIMDAR    = ' DAR'; // Dark
    public const string DIMOFF    = ' OFF'; // Dimmer off

    public const string SSHOSALS    = 'SSHOSALS'; //Auto Lip Sync
    public const string SSHOSALSON  = ' ON'; //Auto Lip Sync On
    public const string SSHOSALSOFF = ' OFF'; //Auto Lip Sync Off

    public const string PSCES    = 'PSCES'; // Center Spread
    public const string PSCESON  = ' ON'; // Center Spread On
    public const string PSCESOFF = ' OFF'; // Center Spread Off

    public const string PSSPV    = 'PSSPV'; // Speaker Virtualizer
    public const string PSSPVON  = ' ON'; // Speaker Virtualizer On
    public const string PSSPVOFF = ' OFF'; // Speaker Virtualizer Off

    public const string PSNEURAL    = 'PSNEURAL'; // Center Spread
    public const string PSNEURALON  = ' ON'; // Center Spread On
    public const string PSNEURALOFF = ' OFF'; // Center Spread Off

    public const string PSBSC = 'PSBSC'; // Bass Sync

    public const string PSDEH     = 'PSDEH'; // Dialog Enhancer
    public const string PSDEHOFF  = ' OFF'; // Dialog Enhancer Off
    public const string PSDEHMED  = ' MED'; // Dialog Enhancer Medium
    public const string PSDEHLOW  = ' LOW'; // Dialog Enhancer Low
    public const string PSDEHHIGH = ' HIGH'; // Dialog Enhancer High

    public const string PSAUROST     = 'PSAUROST'; // Auro Matic 3D Strength
    public const string PSAUROSTUP   = ' UP'; // Auro Matic 3D Strength Up
    public const string PSAUROSTDOWN = ' DOWN'; // Auro Matic 3D Strength Down

    public const string PSAUROPR    = 'PSAUROPR'; // Auro Matic 3D Present
    public const string PSAUROPRSMA = ' SMA'; // Auro Matic 3D Present Small
    public const string PSAUROPRMED = ' MED'; // Auro Matic 3D Present Medium
    public const string PSAUROPRLAR = ' LAR'; // Auro Matic 3D Present Large
    public const string PSAUROPRSPE = ' SPE'; // Auro Matic 3D Present SPE

    public const string PSAUROMODE     = 'PSAUROMODE'; // Auro 3D Mode
    public const string PSAUROMODEDRCT = ' DRCTSMA'; // Auro 3D Mode Direct
    public const string PSAUROMODEEXP  = ' EXP'; // Auro 3D Mode Channel Expansion

    public const string PSDIRAC = 'PSDIRAC'; //Dirac Live Filter

    public const string PSCEX    = 'PSCEX'; // Channel Expander
    public const string PSCEXOFF = ' OFF'; // Channel Expander Off
    public const string PSCEXLOW = ' LOW'; // Channel Expander Low
    public const string PSCEXHI  = ' HI'; // Channel Expander High

    public const string PSSURLEV    = 'PSSURLEV'; // Surround Level Compensation
    public const string SURLEVOFF   = ' OFF'; // Surround Level Compensation Off
    public const string SURLEVLIT   = ' LIT'; // Surround Level Compensation Light
    public const string SURLEVMED   = ' MED'; // Surround Level Compensation Medium
    public const string SURLEVHEV   = ' HEV'; // Surround Level Compensation Heavy

    public const string PSDACFIL      = 'PSDACFIL'; // DAC Filter (nur Marantz)
    public const string PSDACFILMODE1 = ' MODE1'; // DAC Filter Mode 1
    public const string PSDACFILMODE2 = ' MODE2'; // DAC Filter Mode 2

    public const string CVSHL   = 'CVSHL'; // Surround Height Left
    public const string CVSHR   = 'CVSHR'; // Surround Height Right
    public const string CVTS    = 'CVTS'; // Top Surround
    public const string CVCH    = 'CVCH'; // Center Height
    public const string CVZRL   = 'CVZRL'; // Reset Channel Volume Status

    public const string CVTFL = 'CVTFL'; // Top Front Left
    public const string CVTFR = 'CVTFR'; // Top Front Right
    public const string CVTML = 'CVTML'; // Top Middle Left
    public const string CVTMR = 'CVTMR'; // Top Middle Right
    public const string CVTRL = 'CVTRL'; // Top Rear Left
    public const string CVTRR = 'CVTRR'; // Top Rear Right
    public const string CVRHL = 'CVRHL'; // Rear Height Left
    public const string CVRHR = 'CVRHR'; // Rear Height Right
    public const string CVFDL = 'CVFDL'; // Front Dolby Left
    public const string CVFDR = 'CVFDR'; // Front Dolby Right
    public const string CVSDL = 'CVSDL'; // Surround Dolby Left
    public const string CVSDR = 'CVSDR'; // Surround Dolby Right
    public const string CVBDL = 'CVBDL'; // Back Dolby Left
    public const string CVBDR = 'CVBDR'; // Back Dolby Right
    public const string CVTTR = 'CVTTR'; // Tactile Transducer
}
