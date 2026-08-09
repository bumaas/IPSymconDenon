<?php

declare(strict_types=1);

#[AllowDynamicProperties] class DENONIPSProfiles extends stdClass
{
    private $Logger_Dbg;

    private bool  $debug = false; //wird im Constructor gesetzt

    private mixed $AVRType;
    private array $profiles;

    public const string ManufacturerDenon   = 'Denon';
    public const string ManufacturerMarantz = 'Marantz';
    public const string ManufacturerNone    = 'none';

    //Profiltype
    public const string ptPower        = 'Power';
    public const string ptMasterVolume = 'MasterVolume';
    public const string ptBalance      = 'Balance';

    public const string ptChannelVolumeFL = 'ChannelVolumeFL';
    public const string ptChannelVolumeFR = 'ChannelVolumeFR';
    public const string ptChannelVolumeC  = 'ChannelVolumeC';
    public const string ptChannelVolumeSW = 'ChannelVolumeSW';
    public const string ptChannelVolumeSW2 = 'ChannelVolumeSW2';
    public const string ptChannelVolumeSW3 = 'ChannelVolumeSW3';
    public const string ptChannelVolumeSW4 = 'ChannelVolumeSW4';
    public const string ptChannelVolumeSL  = 'ChannelVolumeSL';
    public const string ptChannelVolumeSR = 'ChannelVolumeSR';
    public const string ptChannelVolumeSBL = 'ChannelVolumeSBL';
    public const string ptChannelVolumeSBR = 'ChannelVolumeSBR';
    public const string ptChannelVolumeSB  = 'ChannelVolumeSB';
    public const string ptChannelVolumeFHL = 'ChannelVolumeFHL';
    public const string ptChannelVolumeFHR = 'ChannelVolumeFHR';
    public const string ptChannelVolumeFWL = 'ChannelVolumeFWL';
    public const string ptChannelVolumeFWR = 'ChannelVolumeFWR';
    public const string ptMainMute         = 'MainMute';
    public const string ptInputSource = 'Inputsource';
    public const string ptMainZonePower = 'MainZonePower';
    public const string ptInputMode     = 'InputMode';
    public const string ptDigitalInputMode = 'DigitalInputMode';
    public const string ptVideoSelect      = 'VideoSelect';
    public const string ptSleep       = 'Sleep';
    public const string ptSurroundMode = 'SurroundMode';
    public const string ptQuickSelect  = 'QuickSelect';
    public const string ptSmartSelect = 'SmartSelect';
    public const string ptHDMIMonitor = 'HDMIMonitor';
    public const string ptASP         = 'ASP';
    public const string ptResolution = 'Resolution';
    public const string ptResolutionHDMI = 'ResolutionHDMI';
    public const string ptHDMIAudioOutput = 'HDMIAudioOutput';
    public const string ptVideoProcessingMode = 'VideoProcessingMode';
    public const string ptToneCTRL            = 'ToneCTRL';
    public const string ptSurroundBackMode = 'SurroundBackMode';
    public const string ptSurroundPlayMode = 'SurroundPlayMode';
    public const string ptFrontHeight      = 'FrontHeight';
    public const string ptPLIIZHeightGain = 'PLIIZHeightGain';
    public const string ptSpeakerOutput   = 'SpeakerOutputFront';
    public const string ptMultiEQMode   = 'MultiEQMode';
    public const string ptDynamicEQ   = 'DynamicEQ';
    public const string ptAudysseyLFC = 'AudysseyLFC';
    public const string ptAudysseyContainmentAmount = 'AudysseyContainmantAmount';
    public const string ptReferenceLevel            = 'ReferenceLevel';
    public const string ptDiracLiveFilter    = 'DiracLiveFilter';
    public const string ptDynamicVolume   = 'DynamicVolume';
    public const string ptAudysseyDSX   = 'AudysseyDSX';
    public const string ptStageWidth  = 'StageWidth';
    public const string ptStageHeight = 'StageHeight';
    public const string ptBassLevel   = 'BassLevel';
    public const string ptTrebleLevel = 'TrebleLevel';
    public const string ptLoudnessManagement = 'LoudnessManagement';
    public const string ptDynamicRangeCompression = 'DynamicRangeCompression';
    public const string ptMDAX                    = 'MDAX';
    public const string ptDynamicCompressor = 'DynamicCompressor';
    public const string ptCenterLevelAdjust = 'CenterLevelAdjust';
    public const string ptLFELevel          = 'LFELevel';
    public const string ptLFE71Level = 'LFE71Level';
    public const string ptEffectLevel = 'EffectLevel';
    public const string ptDelay       = 'Delay';
    public const string ptAFDM  = 'AFDM';
    public const string ptPanorama = 'Panorama';
    public const string ptDimension = 'Dimension';
    public const string ptDialogControl = 'DialogControl';
    public const string ptCenterWidth   = 'CenterWidth';
    public const string ptCenterImage = 'CenterImage';
    public const string ptCenterGain  = 'CenterGain';
    public const string ptSubwoofer  = 'Subwoofer';
    public const string ptRoomSize  = 'RoomSize';
    public const string ptAudioDelay = 'AudioDelay';
    public const string ptAudioRestorer = 'AudioRestorer';
    public const string ptFrontSpeaker  = 'FrontSpeaker';
    public const string ptContrast     = 'Contrast';
    public const string ptBrightness = 'Brightness';
    public const string ptSaturation = 'Saturation';
    public const string ptChromalevel = 'Chromalevel';
    public const string ptHue         = 'Hue';
    public const string ptDigitalNoiseReduction = 'DNRDirectChange';
    public const string ptPictureMode           = 'PictureMode';
    public const string ptEnhancer       = 'Enhancer';
    public const string ptBluetoothTransmitter = 'BluetoothTransmitter';
    public const string ptBluetoothLevel       = 'BluetoothLevel';
    public const string ptChannelLevelMonitoring = 'ChannelLevelMonitoring';
    public const string ptHDMIHotPlugTest        = 'HDMIHotPlugTest';
    public const string ptChannelExpander        = 'ChannelExpander';
    public const string ptSurroundLevelCompensation = 'SurroundLevelCompensation';
    public const string ptDACFilter                 = 'DACFilter';
    public const string ptSpeakerPreset        = 'SpeakerPreset';

    public const string ptZone2Power       = 'Zone2Power';
    public const string ptZone2InputSource = 'Zone2InputSource';
    public const string ptZone2Volume      = 'Zone2Volume';
    public const string ptZone2Mute   = 'Zone2Mute';
    public const string ptZone2ChannelSetting = 'Zone2ChannelSetting';
    public const string ptZone2ChannelVolumeFL = 'Zone2ChannelVolumeFL';
    public const string ptZone2ChannelVolumeFR = 'Zone2ChannelVolumeFR';
    public const string ptZone2HPF             = 'Zone2HPF';
    public const string ptZone2Bass     = 'Zone2Bass';
    public const string ptZone2Treble = 'Zone2Treble';
    public const string ptZone2QuickSelect = 'Zone2QuickSelect';
    public const string ptZone2SmartSelect = 'Zone2SmartSelect';
    public const string ptZone2Sleep       = 'Zone2Sleep';

    public const string ptZone3InputSource = 'Zone3InputSource';
    public const string ptZone3Volume      = 'Zone3Volume';
    public const string ptZone3Mute   = 'Zone3Mute';
    public const string ptZone3ChannelSetting = 'Zone3ChannelSetting';
    public const string ptZone3ChannelVolumeFL = 'Zone3ChannelVolumeFL';
    public const string ptZone3ChannelVolumeFR = 'Zone3ChannelVolumeFR';
    public const string ptZone3HPF             = 'Zone3HPF';
    public const string ptZone3Bass     = 'Zone3Bass';
    public const string ptZone3Treble = 'Zone3Treble';
    public const string ptZone3QuickSelect = 'Zone3QuickSelect';
    public const string ptZone3SmartSelect = 'Zone3SmartSelect';
    public const string ptZone3Sleep       = 'Zone3Sleep';

    public const string ptCinemaEQ = 'CinemaEQ';
    public const string ptHTEQ     = 'HTEQ';
    public const string ptDynamicRange = 'DynamicRange';
    public const string ptPreset       = 'Preset';
    public const string ptZone2Name = 'Zone2Name';
    public const string ptZone3Power = 'Zone3Power';
    public const string ptZone3Name  = 'Zone3Name';
    public const string ptNavigation = 'Navigation';
    public const string ptNavigationNetwork = 'NavigationNetwork';
    public const string ptSubwooferATT      = 'SubwooferATT';
    //public const ptDCOMPDirectChange = 'DCOMPDirectChange';
    public const string ptDolbyVolumeLeveler = 'DolbyVolumeLeveler';
    public const string ptDolbyVolumeModeler = 'DolbyVolumeModeler';
    public const string ptVerticalStretch    = 'VerticalStretch';
    public const string ptDolbyVolume     = 'DolbyVolume';
    public const string ptFriendlyName = 'FriendlyName';
    public const string ptMainZoneName = 'MainZoneName';
    public const string ptTopMenuLink  = 'TopMenuLink';
    public const string ptModel       = 'Model';
    public const string ptGUIMenuSourceSelect = 'GUIMenuSourceSelect';
    public const string ptGUIMenuSetup        = 'GUIMenuSetup';
    public const string ptSurroundDisplay = 'SurroundDisplay';
    public const string ptDisplay         = 'Display';
    public const string ptGraphicEQ = 'GraphicEQ';
    public const string ptHeadphoneEQ = 'HeadphoneEQ';
    public const string ptDimmer      = 'Dimmer';
    public const string ptDialogLevelAdjust = 'DialogLevelAdjust';
    public const string ptMAINZONEAutoStandbySetting = 'MAINZONEAutoStandbySetting';
    public const string ptMAINZONEECOModeSetting     = 'MAINZONEECOModeSetting';
    public const string ptCenterSpread           = 'Centerspread';
    public const string ptSpeakerVirtualizer = 'SpeakerVirtualizer';
    public const string ptNeural             = 'Neural';
    public const string ptAllZoneStereo = 'AllZoneStereo';
    public const string ptAutoLipSync   = 'AutoLipSync';
    public const string ptBassSync    = 'BassSync';
    public const string ptSubwooferLevel = 'SubwooferLevel';
    public const string ptSubwoofer2Level = 'Subwoofer2Level';
    public const string ptSubwoofer3Level = 'Subwoofer3Level';
    public const string ptSubwoofer4Level = 'Subwoofer4Level';
    public const string ptDialogEnhancer  = 'DialogEnhancer';
    public const string ptAuroMatic3DPreset = 'AuroMatic3DPreset';
    public const string ptAuroMatic3DStrength = 'AuroMatic3DStrength';
    public const string ptAuro3DMode          = 'Auro3DMode';
    public const string ptTopFrontLch  = 'TopFrontLch';
    public const string ptTopFrontRch = 'TopFrontRch';
    public const string ptTopMiddleLch = 'TopMiddleLch';
    public const string ptTopMiddleRch = 'TopMiddleRch';
    public const string ptTopRearLch   = 'TopRearLch';
    public const string ptTopRearRch = 'TopRearRch';
    public const string ptRearHeightLch = 'RearHeightLch';
    public const string ptRearHeightRch = 'RearHeightRch';
    public const string ptFrontDolbyLch = 'FrontDolbyLch';
    public const string ptFrontDolbyRch = 'FrontDolbyRch';
    public const string ptSurroundDolbyLch = 'SurroundDolbyLch';
    public const string ptSurroundDolbyRch = 'SurroundDolbyRch';
    public const string ptBackDolbyLch     = 'BackDolbyLch';
    public const string ptBackDolbyRch = 'BackDolbyRch';
    public const string ptSurroundHeightLch = 'SurroundHeightLch';
    public const string ptSurroundHeightRch = 'SurroundHeightRch';
    public const string ptTopSurround       = 'TopSurround';
    public const string ptCenterHeight = 'CenterHeight';
    public const string ptChannelVolumeReset = 'ChannelVolumeReset';
    public const string ptTactileTransducer  = 'TactileTransducer';
    public const string ptZone2HDMIAudio    = 'Zone2HDMIAudio';
    public const string ptZone2AutoStandbySetting = 'Zone2AutoStandbySetting';
    public const string ptZone3AutoStandbySetting = 'Zone3AutoStandbySetting';

    public const string ptTunerAnalogPreset = 'TunerAnalogPresets';
    public const string ptTunerAnalogBand   = 'TunerAnalogBand';
    public const string ptTunerAnalogMode = 'TunerAnalogMode';

    public const string ptSYSMI = 'SysMI';
    public const string ptSYSDA = 'SysDA';
    public const string ptSSINFAISFSV = 'SsInfAISFSV';

    public static array $order = [
        //Info Display
        self::ptMainZoneName,
        self::ptModel,

        //AVR Infos
        self::ptSYSMI,
        self::ptSYSDA,
        self::ptSSINFAISFSV,

        //Power Settings
        self::ptPower,
        self::ptMainZonePower,
        self::ptMainMute,
        self::ptSleep,
        self::ptMAINZONEAutoStandbySetting,
        self::ptMAINZONEECOModeSetting,

        //Input Settings
        self::ptInputSource,
        self::ptQuickSelect,
        self::ptSmartSelect,
        self::ptDigitalInputMode,
        self::ptInputMode,
        self::ptVideoSelect,

        //Surround Mode
        self::ptSurroundMode,
        self::ptSurroundDisplay,
        self::ptDolbyVolume,
        self::ptDolbyVolumeLeveler,
        self::ptDolbyVolumeModeler,

        //OnScreenDisplay
        self::ptDisplay,
        self::ptNavigationNetwork,

        //Channel Volumes
        self::ptMasterVolume,
        self::ptBalance,
        self::ptChannelVolumeFL,
        self::ptChannelVolumeFR,
        self::ptChannelVolumeC,
        self::ptChannelVolumeSW,
        self::ptChannelVolumeSW2,
        self::ptChannelVolumeSW3,
        self::ptChannelVolumeSW4,
        self::ptChannelVolumeSL,
        self::ptChannelVolumeSR,
        self::ptChannelVolumeSBL,
        self::ptChannelVolumeSBR,
        self::ptChannelVolumeSB,
        self::ptChannelVolumeFHL,
        self::ptChannelVolumeFHR,
        self::ptChannelVolumeFWL,
        self::ptChannelVolumeFWR,
        self::ptTopFrontLch,
        self::ptTopFrontRch,
        self::ptTopMiddleLch,
        self::ptTopMiddleRch,
        self::ptTopRearLch,
        self::ptTopRearRch,
        self::ptRearHeightLch,
        self::ptRearHeightRch,
        self::ptFrontDolbyLch,
        self::ptFrontDolbyRch,
        self::ptSurroundDolbyLch,
        self::ptSurroundDolbyRch,
        self::ptBackDolbyLch,
        self::ptBackDolbyRch,
        self::ptSurroundHeightLch,
        self::ptSurroundHeightRch,
        self::ptTopSurround,
        self::ptCenterHeight,
        self::ptChannelVolumeReset,
        self::ptTactileTransducer,

        //Sound Processing (Audio Setting)
        self::ptFrontSpeaker,
        self::ptSpeakerOutput,
        self::ptFrontHeight,
        self::ptSubwoofer,
        self::ptToneCTRL,
        self::ptBassLevel,
        self::ptTrebleLevel,
        self::ptLoudnessManagement,
        self::ptBassSync,
        self::ptDialogEnhancer,
        self::ptSubwooferLevel,
        self::ptSubwoofer2Level,
        self::ptSubwoofer3Level,
        self::ptSubwoofer4Level,
        self::ptDialogLevelAdjust,
        self::ptCenterLevelAdjust,
        self::ptLFELevel,
        self::ptLFE71Level,
        self::ptPanorama,
        self::ptDimension,
        self::ptCenterWidth,
        self::ptCenterSpread,
        self::ptCenterImage,
        self::ptCenterGain,
        self::ptDialogControl,
        self::ptNeural,
        self::ptSpeakerVirtualizer,
        self::ptSurroundPlayMode,
        self::ptPLIIZHeightGain,
        self::ptAudysseyDSX,
        self::ptStageWidth,
        self::ptStageHeight,
        self::ptCinemaEQ,
        self::ptHTEQ,
        self::ptMultiEQMode,
        self::ptDynamicEQ,
        self::ptReferenceLevel,
        self::ptDiracLiveFilter,
        self::ptDynamicVolume,
        self::ptSurroundLevelCompensation,
        self::ptChannelExpander,
        self::ptDACFilter,
        self::ptAudysseyLFC,
        self::ptAudysseyContainmentAmount,
        self::ptGraphicEQ,
        self::ptHeadphoneEQ,
        self::ptDynamicRangeCompression,
        self::ptDynamicCompressor,
        self::ptMDAX,
        self::ptAudioDelay,
        self::ptAuroMatic3DPreset,
        self::ptAuroMatic3DStrength,
        self::ptAuro3DMode,
        self::ptEffectLevel, // only Denon
        self::ptAFDM, // only Denon
        self::ptRoomSize, // only Denon
        self::ptSurroundBackMode, //only Denon
        self::ptDelay, //only Denon
        self::ptSubwooferATT, //only Denon
        self::ptAudioRestorer, // only Denon

        self::ptBluetoothTransmitter,
        self::ptBluetoothLevel,
        self::ptChannelLevelMonitoring,
        self::ptSpeakerPreset,

        //Video
        self::ptPictureMode,
        self::ptContrast,
        self::ptBrightness,
        self::ptSaturation,
        self::ptChromalevel,
        self::ptHue,
        self::ptDigitalNoiseReduction,
        self::ptEnhancer,
        self::ptHDMIMonitor,
        self::ptResolution,
        self::ptResolutionHDMI,
        self::ptVideoProcessingMode,
        self::ptHDMIAudioOutput,
        self::ptASP,
        self::ptVerticalStretch,

        //GUI
        self::ptGUIMenuSetup,
        self::ptGUIMenuSourceSelect,
        self::ptNavigation,
        self::ptAllZoneStereo,
        self::ptDimmer,
        self::ptAutoLipSync,
        self::ptHDMIHotPlugTest,

        //Zone 2
        self::ptZone2Name,
        self::ptZone2Power,
        self::ptZone2Mute,
        self::ptZone2Volume,
        self::ptZone2InputSource,
        self::ptZone2ChannelSetting,
        self::ptZone2ChannelVolumeFL,
        self::ptZone2ChannelVolumeFR,
        self::ptZone2Bass,
        self::ptZone2Treble,
        self::ptZone2QuickSelect,
        self::ptZone2HPF,
        self::ptZone2HDMIAudio,
        self::ptZone2Sleep,
        self::ptZone2AutoStandbySetting,
        //Zone 3
        self::ptZone3Name,
        self::ptZone3Power,
        self::ptZone3Mute,
        self::ptZone3Volume,
        self::ptZone3InputSource,
        self::ptZone3ChannelSetting,
        self::ptZone3ChannelVolumeFL,
        self::ptZone3ChannelVolumeFR,
        self::ptZone3Bass,
        self::ptZone3Treble,
        self::ptZone3QuickSelect,
        self::ptZone3HPF,
        self::ptZone3Sleep,
        self::ptZone3AutoStandbySetting,

        //Tuner
        self::ptTunerAnalogPreset,
        self::ptTunerAnalogBand,
        self::ptTunerAnalogMode,
    ];

    public function __construct(?string $AVRType = null, ?array $InputMapping = null, ?callable $Logger_Dbg = null)
    {
        if (isset($Logger_Dbg)){
            $this->debug = true;
            $this->Logger_Dbg = $Logger_Dbg;
            call_user_func($this->Logger_Dbg, __CLASS__ . '::' . __FUNCTION__, 'AVRType: ' . ($AVRType ?? 'null') . ', InputMapping: ' . ($InputMapping === null ? 'null' : json_encode(
                                                $InputMapping,
                                                JSON_THROW_ON_ERROR
                                            )));
        }

        $assRange00to98_add05step = $this->GetAssociationOfAsciiTodB('00', '98', '80', 0.5, true, false);
        $assRange00to98 = $this->GetAssociationOfAsciiTodB('00', '98', '80', 1, false, false);
        $assRange38to62 = $this->GetAssociationOfAsciiTodB('38', '62', '50');
        $assRange38to62_add05step = $this->GetAssociationOfAsciiTodB('38', '62', '50', 0.5, true);
        $assRange00to10_stepwide_01 = $this->GetAssociationOfAsciiTodB('00', '10', '00', 0.1, false, true, false, 0.1);
        $assRange000to200 = $this->GetAssociationOfAsciiTodB('000', '200', '000');
        $assRange000to300 = $this->GetAssociationOfAsciiTodB('000', '300', '000');
        $assRange00to10_invert = $this->GetAssociationOfAsciiTodB('00', '10', '00', 1, false, true, true);
        $assRange00to15_invert = $this->GetAssociationOfAsciiTodB('00', '15', '00', 1, false, true, true);
        $assRange30to90 = $this->GetAssociationOfAsciiTodB('30', '90', '80'); //Bluetooth Level: 30 = -50 dB, 80 = 0 dB, 90 = +10 dB
        $assRange44to56 = $this->GetAssociationOfAsciiTodB('44', '56', '50');
        $assRange40to60 = $this->GetAssociationOfAsciiTodB('40', '60', '50');
        $assRange00to06 = $this->GetAssociationOfAsciiTodB('00', '06', '00');
        $assRange00to07 = $this->GetAssociationOfAsciiTodB('00', '07', '00');
        $assRange00to12 = $this->GetAssociationOfAsciiTodB('00', '12', '00');
        $assRange00to15 = $this->GetAssociationOfAsciiTodB('00', '15', '00');
        $assRange00to16 = $this->GetAssociationOfAsciiTodB('00', '16', '00');
        $assRange000to120_ptSleep = $this->GetAssociationOfAsciiTodB('000', '120', '000', 10, false, false);
        $assRange000to120_ptSleep[0] = ['OFF', 0];
        $assRangeA1toG8 = $this->GetAssociationFromA1toG8();
        $assRange00to56 = $this->GetAssociationFrom00to56();

        //ID -> VariablenIdent, VariablenName
        // hier werden alle Variablen und ihre Profile vordefiniert
        // eine Definition hat den Aufbau
        // Key: ID =>
        // - Type: Variablentyp (boolean, integer, float oder string)
        // - Ident: Variablenident
        // - Name: Variablenname
        // - PropertyName (im Formular)
        // - Profilesettings: Icon, Praefix, Suffix, Minimum, Maximum, Schrittweite, Nachkommastellen
        // - Associations: die Assoziationen sind vom Variablentyp abhängig
        //          boolean:    <true/false, Subcommando>
        //          integer:    <Value, Label, Subcommand>
        //          float:
        //          string:     -
        //- IndividualStatusRequest: wenn abweichend von '<ident> ?', also z.B. ohne Blank
        // Boolean Variablen

        $this->profiles = [
            self::ptPower => ['Type'                     => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PW, 'Name' => 'Power',
                'PropertyName'                           => self::ptPower,
                'Associations'                           => [
                    [false, DENON_API_Commands::PWSTANDBY],
                    [true, DENON_API_Commands::PWON],
                ],
                'IndividualStatusRequest' => 'PW?',
            ],
            self::ptMainZonePower => ['Type'             => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::ZM, 'Name' => 'MainZone Power',
                'PropertyName'                           => self::ptMainZonePower,
                'Associations'                           => [
                    [false, DENON_API_Commands::ZMOFF],
                    [true, DENON_API_Commands::ZMON], ],
                'IndividualStatusRequest' => 'ZM?',
            ],
            self::ptCinemaEQ => ['Type'                     => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSCINEMAEQ, 'Name' => 'Cinema EQ',
                'PropertyName'                              => 'CinemaEQ',
                'Associations'                              => [
                    [false, DENON_API_Commands::CINEMAEQOFF],
                    [true, DENON_API_Commands::CINEMAEQON],
                ],
                'IndividualStatusRequest' => 'PSCINEMA EQ. ?',
            ],
            self::ptHTEQ => ['Type'                         => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSHTEQ, 'Name' => 'HT-EQ',
                'PropertyName'                              => 'HTEQ',
                'Associations'                              => [
                    [false, DENON_API_Commands::HTEQOFF],
                    [true, DENON_API_Commands::HTEQON],
                ], ],
            self::ptDynamicEQ => ['Type'                     => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSDYNEQ, 'Name' => 'Dynamic EQ',
                'PropertyName'                               => 'DynamicEQ',
                'Associations'                               => [
                    [false, DENON_API_Commands::DYNEQOFF],
                    [true, DENON_API_Commands::DYNEQON],
                ], ],
            self::ptAudysseyLFC => ['Type'                     => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSLFC, 'Name' => 'Audyssey LFC',
                'PropertyName'                                 => 'AudysseyLFC',
                'Associations'                                 => [
                    [false, DENON_API_Commands::LFCOFF],
                    [true, DENON_API_Commands::LFCON],
                ], ],
            self::ptFrontHeight => ['Type'               => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSFH, 'Name' => 'Front Height',
                'PropertyName'                           => 'FrontHeight',
                'Associations'                           => [
                    [false, DENON_API_Commands::PSFHOFF],
                    [true, DENON_API_Commands::PSFHON],
                ],
                'IndividualStatusRequest' => 'PSFH: ?',
            ],
            self::ptMainMute => ['Type'                  => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::MU, 'Name' => 'Main Mute',
                'PropertyName'                           => self::ptMainMute,
                'Associations'                           => [
                    [false, DENON_API_Commands::MUOFF],
                    [true, DENON_API_Commands::MUON],
                ],
                'IndividualStatusRequest' => 'MU?',
            ],
            self::ptPanorama => ['Type'                  => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSPAN, 'Name' => 'Panorama',
                'PropertyName'                           => 'Panorama',
                'Associations'                           => [
                    [false, DENON_API_Commands::PANOFF],
                    [true, DENON_API_Commands::PANON],
                ], ],
            self::ptToneCTRL => ['Type'                  => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSTONECTRL, 'Name' => 'Tone CTRL',
                'PropertyName'                           => 'ToneCTRL',
                'Associations'                           => [
                    [false, DENON_API_Commands::PSTONECTRLOFF],
                    [true, DENON_API_Commands::PSTONECTRLON],
                ],
                'IndividualStatusRequest' => 'PSTONE CTRL: ?',
            ],
            self::ptVerticalStretch => ['Type'             => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::VSVST, 'Name' => 'Vertical Stretch',
                'PropertyName'                             => 'VerticalStretch',
                'Associations'                             => [
                    [false, DENON_API_Commands::VSTOFF],
                    [true, DENON_API_Commands::VSTON],
                ], ],
            self::ptDolbyVolume => ['Type'               => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSDOLVOL, 'Name' => 'Dolby Volume',
                'PropertyName'                           => 'DolbyVolume',
                'Associations'                           => [
                    [false, DENON_API_Commands::DOLVOLOFF],
                    [true, DENON_API_Commands::DOLVOLON],
                ], ],
            self::ptAFDM => ['Type'                      => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSAFD, 'Name' => 'Auto Flag Detect Mode',
                'PropertyName'                           => 'AFDM',
                'Associations'                           => [
                    [false, DENON_API_Commands::AFDOFF],
                    [true, DENON_API_Commands::AFDON],
                ], ],
            self::ptSubwoofer => ['Type'                 => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSSWR, 'Name' => 'Subwoofer',
                'PropertyName'                           => 'Subwoofer',
                'Associations'                           => [
                    [false, DENON_API_Commands::PSSWROFF],
                    [true, DENON_API_Commands::PSSWRON],
                ], ],
            self::ptSubwooferATT => ['Type'              => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSATT, 'Name' => 'Subwoofer ATT',
                'PropertyName'                           => 'SubwooferATT',
                'Associations'                           => [
                    [false, DENON_API_Commands::PSSWROFF],
                    [true, DENON_API_Commands::PSSWRON],
                ], ],
            self::ptLoudnessManagement  => ['Type'            => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSLOM, 'Name' => 'Loudness Management',
                'PropertyName'                                => 'LoudnessManagement',
                'Associations'                                => [
                    [false, DENON_API_Commands::PSLOMOFF],
                    [true, DENON_API_Commands::PSLOMON],
                ], ],
            self::ptGUIMenuSetup        => ['Type'         => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::MNMEN, 'Name' => 'GUI Setup Menu',
                'PropertyName'                             => 'GUIMenu',
                'Associations'                             => [
                    [false, DENON_API_Commands::MNMENOFF],
                    [true, DENON_API_Commands::MNMENON],
                ], ],
            self::ptGUIMenuSourceSelect => ['Type'         => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::MNSRC, 'Name' => 'GUI Source Select Menu',
                'PropertyName'                             => 'GUIMenuSource',
                'Associations'                             => [
                    [false, DENON_API_Commands::MNSRCOFF],
                    [true, DENON_API_Commands::MNSRCON],
                ], ],
            self::ptGraphicEQ => ['Type'                     => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSGEQ, 'Name' => 'Graphic EQ',
                'PropertyName'                               => 'GraphicEQ',
                'Associations'                               => [
                    [false, DENON_API_Commands::PSGEQOFF],
                    [true, DENON_API_Commands::PSGEQON],
                ], ],
            self::ptHeadphoneEQ => ['Type'                   => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSHEQ, 'Name' => 'Headphone EQ',
                'PropertyName'                               => 'HeadphoneEQ',
                'Associations'                               => [
                    [false, DENON_API_Commands::PSHEQOFF],
                    [true, DENON_API_Commands::PSHEQON],
                ], ],
            self::ptCenterSpread    => ['Type'                     => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSCES, 'Name' => 'Center Spread',
                'PropertyName'                                     => 'CenterSpread',
                'Associations'                                     => [
                    [false, DENON_API_Commands::PSCESOFF],
                    [true, DENON_API_Commands::PSCESON],
                ], ],
            self::ptSpeakerVirtualizer    => ['Type'                     => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSSPV, 'Name' => 'Speaker Virtualizer',
                'PropertyName'                                           => 'SpeakerVirtualizer',
                'Associations'                                           => [
                    [false, DENON_API_Commands::PSSPVOFF],
                    [true, DENON_API_Commands::PSSPVON],
                ], ],
            self::ptNeural   => ['Type'                     => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::PSNEURAL, 'Name' => 'Neural:X',
                'PropertyName'                              => 'Neural',
                'Associations'                              => [
                    [false, DENON_API_Commands::PSNEURALOFF],
                    [true, DENON_API_Commands::PSNEURALON],
                ], ],
            self::ptAllZoneStereo   => ['Type'                     => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::MNZST, 'Name' => 'All Zone Stereo',
                'PropertyName'                                     => 'AllZoneStereo',
                'Associations'                                     => [
                    [false, DENON_API_Commands::MNZSTOFF],
                    [true, DENON_API_Commands::MNZSTON],
                ], ],
            self::ptAutoLipSync   => ['Type'                       => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::SSHOSALS, 'Name' => 'Auto Lip Sync',
                'PropertyName'                                     => 'AutoLipSync',
                'Associations'                                     => [
                    [false, DENON_API_Commands::SSHOSALSOFF],
                    [true, DENON_API_Commands::SSHOSALSON],
                ], ],
            self::ptZone2Power      => ['Type'             => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::Z2POWER, 'Name' => 'Zone 2 Power',
                'PropertyName'                             => self::ptZone2Power,
                'Associations'                             => [
                    [false, DENON_API_Commands::Z2OFF],
                    [true, DENON_API_Commands::Z2ON],
                ],
                'IndividualStatusRequest' => 'Z2?',
            ],
            self::ptZone2Mute => ['Type'                 => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::Z2MU, 'Name' => 'Zone 2 Mute',
                'PropertyName'                           => self::ptZone2Mute,
                'Associations'                           => [
                    [false, DENON_API_Commands::Z2OFF],
                    [true, DENON_API_Commands::Z2ON],
                ], ],
            self::ptZone2HPF => ['Type'                     => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::Z2HPF, 'Name' => 'Zone 2 HPF',
                'PropertyName'                              => 'Z2HPF',
                'Associations'                              => [
                    [false, DENON_API_Commands::Z2OFF],
                    [true, DENON_API_Commands::Z2ON],
                ], ],
            self::ptZone3Power => ['Type'                => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::Z3POWER, 'Name' => 'Zone 3 Power',
                'PropertyName'                           => self::ptZone3Power,
                'Associations'                           => [
                    [false, DENON_API_Commands::Z3OFF],
                    [true, DENON_API_Commands::Z3ON],
                ],
                'IndividualStatusRequest' => 'Z3?',
            ],
            self::ptZone3Mute => ['Type'                 => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::Z3MU, 'Name' => 'Zone 3 Mute',
                'PropertyName'                           => self::ptZone3Mute,
                'Associations'                           => [
                    [false, DENON_API_Commands::Z3OFF],
                    [true, DENON_API_Commands::Z3ON],
                ], ],

            self::ptZone3HPF => ['Type'                  => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::Z3HPF, 'Name' => 'Zone 3 HPF',
                'PropertyName'                           => 'Z3HPF',
                'Associations'                           => [
                    [false, DENON_API_Commands::Z3OFF],
                    [true, DENON_API_Commands::Z3ON],
                ], ],

            //Ident, Variablenname, Profilesettings
            //Associations: Value, Label, Association
            self::ptBalance => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::BL, 'Name' => 'Balance',
                                   'PropertyName'                        => 'Balance',
                                   'Profilesettings'                     => ['', '', '', 0, 0, 0, 0],
                                   'Associations'                        => [
                                       [-12, 'L 12', 'L12'],
                                       [-11, 'L 11', 'L11'],
                                       [-10, 'L 10', 'L10'],
                                       [-9, 'L 9', 'L9'],
                                       [-8, 'L 8', 'L8'],
                                       [-7, 'L 7', 'L7'],
                                       [-6, 'L 6', 'L6'],
                                       [-5, 'L 5', 'L5'],
                                       [-4, 'L 4', 'L4'],
                                       [-3, 'L 3', 'L3'],
                                       [-2, 'L 2', 'L2'],
                                       [-1, 'L 1', 'L1'],
                                       [0, '0', '0'],
                                       [1, 'R 1', 'R1'],
                                       [2, 'R 2', 'R2'],
                                       [3, 'R 3', 'R3'],
                                       [4, 'R 4', 'R4'],
                                       [5, 'R 5', 'R5'],
                                       [6, 'R 6', 'R6'],
                                       [7, 'R 7', 'R7'],
                                       [8, 'R 8', 'R8'],
                                       [9, 'R 9', 'R9'],
                                       [10, 'R 10', 'R10'],
                                       [11, 'R 11', 'R11'],
                                       [12, 'R 12', 'R12'],
                                   ],
            ],

            self::ptInputSource => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::SI, 'Name' => 'Input Source',
                'PropertyName'                         => 'InputSource',
                'Profilesettings'                      => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                         => [], //are adapted by function SetInputSources()
                'IndividualStatusRequest'              => 'SI?',
            ],
            self::ptZone2InputSource => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::Z2INPUT, 'Name' => 'Zone 2 Input Source',
                'PropertyName'                              => self::ptZone2InputSource,
                'Profilesettings'                           => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                              => [], //are adapted by function SetInputSources()
                'IndividualStatusRequest'                   => 'Z2?',
            ],
            self::ptZone3InputSource => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::Z3INPUT, 'Name' => 'Zone 3 Input Source',
                'PropertyName'                              => self::ptZone3InputSource,
                'Profilesettings'                           => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                              => [], //are adapted by function SetInputSources()
                'IndividualStatusRequest'                   => 'Z3?',
            ],
            self::ptChannelVolumeReset => ['Type'                      => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::CVZRL, 'Name' => 'Channel Volume Reset',
                'PropertyName'                                         => 'ChannelVolumeReset',
                'Profilesettings'                                      => ['Script', '', '', 0, 0, 0, 0],
                'Associations'                                         => [
                    [1, 'Reset', ''],
                ],
                'IndividualStatusRequest' => 'CV?',
            ],
            self::ptNavigation => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::MN, 'Name' => 'Navigation Setup Menu',
                'PropertyName'                        => 'Navigation',
                'Profilesettings'                     => ['Move', '', '', 0, 0, 0, 0],
                'Associations'                        => [
                    [0, 'Left', DENON_API_Commands::MNCLT],
                    [1, 'Down', DENON_API_Commands::MNCDN],
                    [2, 'Up', DENON_API_Commands::MNCUP],
                    [3, 'Right', DENON_API_Commands::MNCRT],
                    [4, 'Enter', DENON_API_Commands::MNENT],
                    [5, 'Return', DENON_API_Commands::MNRTN],
                ],
            ],
            self::ptNavigationNetwork => ['Type'      => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::NS, 'Name' => 'Navigation Network',
                'PropertyName'                        => 'NavigationNetwork',
                'Profilesettings'                     => ['Move', '', '', 0, 0, 0, 0],
                'Associations'                        => [
                    [0, 'Up', DENON_API_Commands::NSUP],
                    [1, 'Down', DENON_API_Commands::NSDOWN],
                    [2, 'Left', DENON_API_Commands::NSLEFT],
                    [3, 'Enter (Play/Pause)', DENON_API_Commands::NSENTER],
                    [4, 'Stop', DENON_API_Commands::NSSTOP],
                    [5, 'Skip <', DENON_API_Commands::NSSKIPMINUS],
                    [6, 'Skip >', DENON_API_Commands::NSSKIPPLUS],
                    [12, 'Page Previous', DENON_API_Commands::NSPAGEPREV],
                    [13, 'Page Next', DENON_API_Commands::NSPAGENEXT],
                ],
            ],
            self::ptQuickSelect => ['Type'                        => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::MSQUICK, 'Name' => 'Quick Select',
                'PropertyName'                                    => 'QuickSelect',
                'Profilesettings'                                 => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                                    => [
                    [0, '-', DENON_API_Commands::MSQUICK0],
                    [1, 'Select 1', DENON_API_Commands::MSQUICK1],
                    [2, 'Select 2', DENON_API_Commands::MSQUICK2],
                    [3, 'Select 3', DENON_API_Commands::MSQUICK3],
                    [4, 'Select 4', DENON_API_Commands::MSQUICK4],
                    [5, 'Select 5', DENON_API_Commands::MSQUICK5],
                    [6, 'Select 6', DENON_API_Commands::MSQUICK6], // erst ab CY2026, wird über MSQUICK_SubCommands gefiltert
                ],
            ],
            self::ptSmartSelect => ['Type'                        => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::MSSMART, 'Name' => 'Smart Select',
                'PropertyName'                                    => 'SmartSelect',
                'Profilesettings'                                 => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                                    => [
                    [0, '-', DENON_API_Commands::MSSMART0],
                    [1, 'Select 1', DENON_API_Commands::MSSMART1],
                    [2, 'Select 2', DENON_API_Commands::MSSMART2],
                    [3, 'Select 3', DENON_API_Commands::MSSMART3],
                    [4, 'Select 4', DENON_API_Commands::MSSMART4],
                    [5, 'Select 5', DENON_API_Commands::MSSMART5],
                ],
            ],
            self::ptDigitalInputMode => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::DC, 'Name' => 'Audio Decode Mode',
                'PropertyName'                              => 'DigitalInputMode',
                'Profilesettings'                           => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                              => [
                    [0, 'Auto', DENON_API_Commands::DCAUTO],
                    [1, 'PCM', DENON_API_Commands::DCPCM],
                    [2, 'DTS', DENON_API_Commands::DCDTS],
                ],
            ],
            self::ptAudysseyDSX => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSDSX, 'Name' => 'Audyssey DSX',
                'PropertyName'                         => 'AudysseyDSX',
                'Profilesettings'                      => ['Speaker', '', '', 0, 0, 0, 0],
                'Associations'                         => [
                    [0, 'Off', DENON_API_Commands::PSDSXOFF],
                    [1, 'Audyssey DSX On(Wide)', DENON_API_Commands::PSDSXONW],
                    [2, 'Audyssey DSX On(Height)', DENON_API_Commands::PSDSXONH],
                    [3, 'Audyssey DSX On(Wide/Height)', DENON_API_Commands::PSDSXONHW],
                ],
            ],

            self::ptSurroundMode => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::MS, 'Name' => 'Surround Mode',
                'PropertyName'                          => self::ptSurroundMode,
                'Profilesettings'                       => ['Melody', '', '', 0, 0, 0, 0],
                'Associations'                          => [
                    [0, 'Movie', DENON_API_Commands::MSMOVIE],
                    [1, 'Music', DENON_API_Commands::MSMUSIC],
                    [2, 'Game', DENON_API_Commands::MSGAME],
                    [3, 'Direct', DENON_API_Commands::MSDIRECT],
                    [4, 'Pure Direct', DENON_API_Commands::MSPUREDIRECT],
                    [5, 'Stereo', DENON_API_Commands::MSSTEREO],
                    [6, 'Standard', DENON_API_Commands::MSSTANDARD],
                    [7, 'Dolby Surround', DENON_API_Commands::MSDOLBYDIGITAL],
                    [8, 'DTS Surround', DENON_API_Commands::MSDTSSURROUND],
                    [9, 'Auro 3D', DENON_API_Commands::MSAURO3D],
                    [10, 'Auro 2D', DENON_API_Commands::MSAURO2DSURR],
                    [11, '7 Channel Stereo', DENON_API_Commands::MS7CHSTEREO],
                    [12, 'Multi Ch Stereo', DENON_API_Commands::MSMCHSTEREO],
                    [13, 'Wide Screen', DENON_API_Commands::MSWIDESCREEN],
                    [14, 'Super Stadium', DENON_API_Commands::MSSUPERSTADIUM],
                    [15, 'Rock Arena', DENON_API_Commands::MSROCKARENA],
                    [16, 'Jazz Club', DENON_API_Commands::MSJAZZCLUB],
                    [17, 'Classic Concert', DENON_API_Commands::MSCLASSICCONCERT],
                    [18, 'Mono Movie', DENON_API_Commands::MSMONOMOVIE],
                    [19, 'Matrix', DENON_API_Commands::MSMATRIX],
                    [20, 'Video Game', DENON_API_Commands::MSVIDEOGAME],
                    [21, 'Virtual', DENON_API_Commands::MSVIRTUAL],
                    //nur anhängen, nie umnummerieren: die Werte stehen so in den Variablen
                    [22, 'Auto', DENON_API_Commands::MSAUTO],
                    [23, 'Neural', DENON_API_Commands::MSNEURAL],
                ],
                'IndividualStatusRequest' => 'MS?',
            ],
            self::ptSurroundPlayMode => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSMODE, 'Name' => 'Surround Play Mode',
                'PropertyName'                              => 'SurroundPlayMode',
                'Profilesettings'                           => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                              => [
                    [0, 'Cinema', DENON_API_Commands::MODECINEMA],
                    [1, 'Music', DENON_API_Commands::MODEMUSIC],
                    [2, 'Game', DENON_API_Commands::MODEGAME],
                    [3, 'Pro Logic', DENON_API_Commands::MODEPROLOGIC],
                ],
                'IndividualStatusRequest' => 'PSMODE: ?',
            ],
            self::ptMultiEQMode => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSMULTEQ, 'Name' => 'Multi EQ Mode',
                'PropertyName'                         => 'MultiEQMode',
                'Profilesettings'                      => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                         => [
                    [0, 'Off', DENON_API_Commands::MULTEQOFF],
                    [1, 'Reference', DENON_API_Commands::MULTEQAUDYSSEY],
                    [2, 'L/R Bypass', DENON_API_Commands::MULTEQBYPLR],
                    [3, 'Flat', DENON_API_Commands::MULTEQFLAT],
                    [4, 'Manual', DENON_API_Commands::MULTEQMANUAL],
                ],
                'IndividualStatusRequest' => 'PSMULTEQ: ?',
            ],
            self::ptAudioRestorer => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSRSTR, 'Name' => 'Audio Restorer',
                'PropertyName'                           => 'AudioRestorer',
                'Profilesettings'                        => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                           => [
                    [0, 'Off', DENON_API_Commands::PSRSTROFF],
                    [1, 'Hoch', DENON_API_Commands::PSRSTRMODE1],
                    [2, 'Mittel', DENON_API_Commands::PSRSTRMODE2],
                    [3, 'Gering', DENON_API_Commands::PSRSTRMODE3],
                ],
            ],
            self::ptFrontSpeaker => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSFRONT, 'Name' => 'Speaker A/B',
                'PropertyName'                          => 'FrontSpeaker',
                'Profilesettings'                       => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                          => [
                    [0, 'Speaker A', DENON_API_Commands::PSFRONTSPA],
                    [1, 'Speaker B', DENON_API_Commands::PSFRONTSPB],
                    [2, 'Speaker A+B', DENON_API_Commands::PSFRONTSPAB],
                ],
                'IndividualStatusRequest' => 'PSFRONT?',
            ],
            self::ptRoomSize => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSRSZ, 'Name' => 'Room Size',
                'PropertyName'                      => 'RoomSize',
                'Profilesettings'                   => ['Sofa', '', '', 0, 0, 0, 0],
                'Associations'                      => [
                    [0, 'Normal', DENON_API_Commands::RSZN],
                    [1, 'Small', DENON_API_Commands::RSZS],
                    [2, 'Small/Medium', DENON_API_Commands::RSZMS],
                    [3, 'Medium', DENON_API_Commands::RSZM],
                    [4, 'Medium/Large', DENON_API_Commands::RSZML],
                    [5, 'Large', DENON_API_Commands::RSZL],
                ],
            ],
            self::ptDynamicCompressor       => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSDCO, 'Name' => 'Dynamic Compressor',
                'PropertyName'                                     => 'DynamicCompressor',
                'Profilesettings'                                  => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                                     => [
                    [0, 'Off', DENON_API_Commands::DCOOFF],
                    [1, 'Low', DENON_API_Commands::DCOLOW],
                    [2, 'Middle', DENON_API_Commands::DCOMID],
                    [3, 'High', DENON_API_Commands::DCOHIGH],
                ],
            ],
            self::ptDynamicRangeCompression => ['Type'                          => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSDRC, 'Name' => 'Dynamic Range Compression',
                'PropertyName'                                                  => 'DynamicRange',
                'Profilesettings'                                               => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                                                  => [
                    [0, 'Off', DENON_API_Commands::DRCOFF],
                    [1, 'Auto', DENON_API_Commands::DRCAUTO],
                    [2, 'Low', DENON_API_Commands::DRCLOW],
                    [3, 'Middle', DENON_API_Commands::DRCMID],
                    [4, 'High', DENON_API_Commands::DRCHI],
                ],
            ],
            self::ptMDAX => ['Type'                          => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSMDAX, 'Name' => 'M-DAX',
                'PropertyName'                               => 'MDAX',
                'Profilesettings'                            => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                               => [
                    [0, 'Off', DENON_API_Commands::MDAXOFF],
                    [1, 'Low', DENON_API_Commands::MDAXLOW],
                    [2, 'Middle', DENON_API_Commands::MDAXMID],
                    [3, 'High', DENON_API_Commands::MDAXHI],
                ],
            ],
            self::ptVideoSelect => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::SV, 'Name' => 'Video Select',
                'PropertyName'                         => 'VideoSelect',
                'Profilesettings'                      => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                         => [
                    [0, 'DVD', DENON_API_Commands::IS_DVD],
                    [1, 'BD', DENON_API_Commands::IS_BD],
                    [2, 'TV', DENON_API_Commands::IS_TV],
                    [3, 'Sat/CBL', DENON_API_Commands::IS_SAT_CBL],
                    [4, 'Sat', DENON_API_Commands::IS_SAT],
                    [5, 'MediaPlayer', DENON_API_Commands::IS_MPLAY],
                    [6, 'VCR', DENON_API_Commands::IS_VCR],
                    [7, 'DVR', DENON_API_Commands::IS_DVR],
                    [8, 'Game', DENON_API_Commands::IS_GAME],
                    [9, 'Game2', DENON_API_Commands::IS_GAME2],
                    [10, 'V.AUX', DENON_API_Commands::IS_VAUX],
                    [11, 'AUX1', DENON_API_Commands::IS_AUX1],
                    [12, 'AUX2', DENON_API_Commands::IS_AUX2],
                    [13, 'CD', DENON_API_Commands::IS_CD],
                    [14, 'Source', DENON_API_Commands::IS_SOURCE],
                    [15, 'On', DENON_API_Commands::IS_ON],
                    [16, 'Off', DENON_API_Commands::IS_OFF],
                    //nur anhängen, nie umnummerieren: die Werte stehen so in den Variablen
                    [17, 'Dock', DENON_API_Commands::IS_DOCK],
                    [18, 'Game1', DENON_API_Commands::IS_GAME1],
                    [19, '8K', DENON_API_Commands::IS_8K],
                    [20, 'AUX3', DENON_API_Commands::IS_AUX3],
                    [21, 'AUX4', DENON_API_Commands::IS_AUX4],
                    [22, 'AUX5', DENON_API_Commands::IS_AUX5],
                    [23, 'AUX6', DENON_API_Commands::IS_AUX6],
                    [24, 'AUX7', DENON_API_Commands::IS_AUX7],
                ],
            ],
            self::ptSurroundBackMode => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSSB, 'Name' => 'Surround Back Mode',
                'PropertyName'                              => 'SurroundBackMode',
                'Profilesettings'                           => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                              => [
                    [0, 'Off', DENON_API_Commands::SBOFF],
                    [1, 'On', DENON_API_Commands::SBON],
                    [2, 'Matrix On', DENON_API_Commands::SBMTRXON],
                    [3, 'PL2X Cinema', DENON_API_Commands::SBPL2XCINEMA],
                    [4, 'PL2X Music', DENON_API_Commands::SBPL2XMUSIC],
                ],
                'IndividualStatusRequest' => 'PSSB: ?',
            ],
            self::ptHDMIMonitor   => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::VSMONI, 'Name' => 'HDMI Monitor',
                'PropertyName'                           => 'HDMIMonitor',
                'Profilesettings'                        => ['TV', '', '', 0, 0, 0, 0],
                'Associations'                           => [
                    [0, 'Auto', DENON_API_Commands::VSMONIAUTO],
                    [1, 'Monitor 1', DENON_API_Commands::VSMONI1],
                    [2, 'Monitor 2', DENON_API_Commands::VSMONI2],
                ],
            ],
            self::ptSpeakerOutput => ['Type'                        => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSSP, 'Name' => 'Effekt Speaker',
                'PropertyName'                                      => 'SpeakerOutputFront',
                'Profilesettings'                                   => ['Speaker', '', '', 0, 0, 0, 0],
                'Associations'                                      => [
                    [0, 'Off', DENON_API_Commands::SPOFF],
                    [1, 'Front Height', DENON_API_Commands::SPFH],
                    [2, 'Front Wide', DENON_API_Commands::SPFW],
                    [3, 'Surround Back', DENON_API_Commands::SPSB],
                    [4, 'Fr.Height & Fr.Wide', DENON_API_Commands::SPHW],
                    [5, 'Surr.Back & Fr.Height', DENON_API_Commands::SPBH],
                    [6, 'Surr.Back & Fr.Wide', DENON_API_Commands::SPBW],
                    [7, 'Floor', DENON_API_Commands::SPFL],
                    [8, 'Height & Floor', DENON_API_Commands::SPHF],
                    [9, 'Front', DENON_API_Commands::SPFR],
                ],
                'IndividualStatusRequest' => 'PSSP: ?',
            ],
            self::ptReferenceLevel   => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSREFLEV, 'Name' => 'Reference Level',
                'PropertyName'                              => 'ReferenceLevel',
                'Profilesettings'                           => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                              => [
                    [0, 'Offset 0', DENON_API_Commands::REFLEV0],
                    [5, 'Offset 5', DENON_API_Commands::REFLEV5],
                    [10, 'Offset 10', DENON_API_Commands::REFLEV10],
                    [15, 'Offset 15', DENON_API_Commands::REFLEV15],
                ],
            ],
            self::ptDiracLiveFilter   => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSDIRAC, 'Name' => 'Dirac Live Filter',
                'PropertyName'                              => 'DiracLiveFilter',
                'Profilesettings'                           => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                              => [
                    [0, 'Off', DENON_API_Commands::DIRACOFF],
                    [1, 'Slot 1', DENON_API_Commands::DIRAC1],
                    [2, 'Slot 2', DENON_API_Commands::DIRAC2],
                    [3, 'Slot 3', DENON_API_Commands::DIRAC3]
                ],
            ],
            self::ptPLIIZHeightGain => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSPHG, 'Name' => 'PLIIZ Height Gain',
                'PropertyName'                             => 'PLIIZHeightGain',
                'Profilesettings'                          => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                             => [
                    [0, 'Low', DENON_API_Commands::PHGLOW],
                    [1, 'Middle', DENON_API_Commands::PHGMID],
                    [2, 'High', DENON_API_Commands::PHGHI],
                ],
            ],
            self::ptDolbyVolumeModeler => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSVOLMOD, 'Name' => 'Dolby Volume Modeler',
                'PropertyName'                                => 'DolbyVolumeModeler',
                'Profilesettings'                             => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                                => [
                    [0, 'Off', DENON_API_Commands::VOLMODOFF],
                    [1, 'Half', DENON_API_Commands::VOLMODHLF],
                    [2, 'Full', DENON_API_Commands::VOLMODFUL],
                ],
            ],
            self::ptDolbyVolumeLeveler => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSVOLLEV, 'Name' => 'Dolby Volume Leveler',
                'PropertyName'                                => 'DolbyVolumeLeveler',
                'Profilesettings'                             => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                                => [
                    [0, 'Low', DENON_API_Commands::VOLLEVLOW],
                    [1, 'Middle', DENON_API_Commands::VOLLEVMID],
                    [2, 'High', DENON_API_Commands::VOLLEVHI],
                ],
            ],
            self::ptVideoProcessingMode => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::VSVPM, 'Name' => 'Video Processing Mode',
                'PropertyName'                                 => 'VideoProcessingMode',
                'Profilesettings'                              => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                                 => [
                    [0, 'Auto', DENON_API_Commands::VPMAUTO],
                    [1, 'Game', DENON_API_Commands::VPGAME],
                    [2, 'Movie', DENON_API_Commands::VPMOVI],
                    [3, 'Bypass', DENON_API_Commands::VPMBYP],
                ],
            ],
            self::ptHDMIAudioOutput => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::VSAUDIO, 'Name' => 'HDMI Audio Output',
                'PropertyName'                             => 'HDMIAudioOutput',
                'Profilesettings'                          => ['TV', '', '', 0, 0, 0, 0],
                'Associations'                             => [
                    [0, 'TV', DENON_API_Commands::AUDIOTV],
                    [1, 'AMP', DENON_API_Commands::AUDIOAMP],
                ],
            ],
            self::ptASP => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::VSASP, 'Name' => 'ASP',
                'PropertyName'                 => 'ASP',
                'Profilesettings'              => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                 => [
                    [0, 'Normal', DENON_API_Commands::ASPNRM],
                    [1, 'Full', DENON_API_Commands::ASPFUL],
                ],
            ],
            self::ptPictureMode => ['Type'                                  => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PVPICT, 'Name' => 'Picture Mode',
                'PropertyName'                                              => 'PictureMode',
                'Profilesettings'                                           => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                                              => [
                    [0, 'Off', DENON_API_Commands::PVPICTOFF],
                    [1, 'Standard', DENON_API_Commands::PVPICTSTD],
                    [2, 'Movie', DENON_API_Commands::PVPICTMOV],
                    [3, 'Vivid', DENON_API_Commands::PVPICTVVD],
                    [4, 'Stream', DENON_API_Commands::PVPICTSTM],
                    [5, 'Custom', DENON_API_Commands::PVPICTCTM],
                    [6, 'ISF Day', DENON_API_Commands::PVPICTDAY],
                    [7, 'ISF Night', DENON_API_Commands::PVPICTNGT],
                ],
                'IndividualStatusRequest' => 'PV?',

            ],
            self::ptDigitalNoiseReduction => ['Type'                        => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PVDNR, 'Name' => 'Digital Noise Reduction',
                'PropertyName'                                              => 'DNRDirectChange',
                'Profilesettings'                                           => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                                              => [
                    [0, 'Off', DENON_API_Commands::PVDNROFF],
                    [1, 'Low', DENON_API_Commands::PVDNRLOW],
                    [2, 'Middle', DENON_API_Commands::PVDNRMID],
                    [3, 'High', DENON_API_Commands::PVDNRHI],
                ],
            ],
            self::ptInputMode => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::SD, 'Name' => 'Audio Input Mode',
                'PropertyName'                       => 'InputMode',
                'Profilesettings'                    => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                       => [
                    [0, 'AUTO', DENON_API_Commands::SDAUTO],
                    [1, 'HDMI', DENON_API_Commands::SDHDMI],
                    [2, 'DIGITAL', DENON_API_Commands::SDDIGITAL],
                    [3, 'ANALOG', DENON_API_Commands::SDANALOG],
                    [4, 'Ext.IN', DENON_API_Commands::SDEXTIN],
                    [5, '7.1 IN', DENON_API_Commands::SD71IN],
                    [6, 'No', DENON_API_Commands::SDNO],
                ],
            ],
            self::ptBluetoothTransmitter => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::BTTX, 'Name' => 'Bluetooth Transmitter',
                'PropertyName'                       => 'BluetoothTransmitter',
                'Profilesettings'                    => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                       => [
                    [0, 'Off', DENON_API_Commands::BTTXOFF],
                    [1, 'On', DENON_API_Commands::BTTXON],
                    [2, 'Bluetooth + Speaker', DENON_API_Commands::BTTXSP],
                    [3, 'Bluetooth only', DENON_API_Commands::BTTXBT],
                ],
            ],
            self::ptChannelLevelMonitoring => ['Type'    => DENONIPSVarType::vtBoolean, 'Ident' => DENON_API_Commands::CLM, 'Name' => 'Channel Level Monitoring',
                'PropertyName'                           => 'ChannelLevelMonitoring',
                'Associations'                           => [
                    [false, DENON_API_Commands::CLMOFF],
                    [true, DENON_API_Commands::CLMON],
                ], ],
            self::ptHDMIHotPlugTest => ['Type'           => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::SYHPT, 'Name' => 'HDMI Hot Plug Test',
                'PropertyName'                           => 'HDMIHotPlugTest',
                'Profilesettings'                        => ['Move', '', '', 0, 0, 0, 0],
                'Associations'                           => [
                    [0, 'High', DENON_API_Commands::SYHPTHIGH],
                    [1, 'Low', DENON_API_Commands::SYHPTLOW],
                    [2, 'Toggle', DENON_API_Commands::SYHPTTOG],
                ],
                // reine Aktion, die Antwort lautet 'SYHPT OK' - kein Statusabruf möglich
                // (der Ident ist deshalb in GetStates() ausgenommen)
            ],
            self::ptSpeakerPreset => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::SPPR, 'Name' => 'Speaker Preset',
                'PropertyName'                       => 'SpeakerPreset',
                'Profilesettings'                    => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                       => [
                    [0, 'Preset 1', DENON_API_Commands::SPPR_1],
                    [1, 'Preset 2', DENON_API_Commands::SPPR_2],
                ],
            ],
            self::ptDialogEnhancer => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSDEH, 'Name' => 'Dialog Enhancer',
                'PropertyName'                            => 'DialogEnhancer',
                'Profilesettings'                         => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                            => [
                    [0, 'Off', DENON_API_Commands::PSDEHOFF],
                    [1, 'Low', DENON_API_Commands::PSDEHLOW],
                    [2, 'Medium', DENON_API_Commands::PSDEHMED],
                    [3, 'High', DENON_API_Commands::PSDEHHIGH],
                ],
            ],
            self::ptAuroMatic3DPreset => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSAUROPR, 'Name' => 'Auro-Matic 3D Preset',
                'PropertyName'                               => 'AuroMatic3DPreset',
                'Profilesettings'                            => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                               => [
                    [0, 'Small', DENON_API_Commands::PSAUROPRSMA],
                    [1, 'Medium', DENON_API_Commands::PSAUROPRMED],
                    [2, 'Large', DENON_API_Commands::PSAUROPRLAR],
                    [3, 'SPE', DENON_API_Commands::PSAUROPRSPE],
                ],
            ],
            self::ptAuro3DMode => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSAUROMODE, 'Name' => 'Auro 3D Mode',
                'PropertyName'                               => 'Auro3DMode',
                'Profilesettings'                            => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                               => [
                    [0, 'Direct', DENON_API_Commands::PSAUROMODEDRCT],
                    [1, 'Ch.Expansion', DENON_API_Commands::PSAUROMODEEXP],
                    [2, 'Large', DENON_API_Commands::PSAUROPRLAR],
                ],
            ],
            self::ptMAINZONEAutoStandbySetting => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::STBY, 'Name' => 'Mainzone Auto Standby',
                'PropertyName'                                        => 'MAINZONEAutoStandbySetting',
                'Profilesettings'                                     => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                                        => [
                    [0, 'Off', DENON_API_Commands::STBYOFF],
                    [1, '15 Min', DENON_API_Commands::STBY15M],
                    [2, '30 Min', DENON_API_Commands::STBY30M],
                    [3, '60 Min', DENON_API_Commands::STBY60M],
                ],
            ],
            self::ptMAINZONEECOModeSetting => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::ECO, 'Name' => 'Mainzone ECO Mode',
                'PropertyName'                                    => 'MAINZONEECOModeSetting',
                'Profilesettings'                                 => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                                    => [
                    [0, 'Off', DENON_API_Commands::ECOOFF],
                    [1, 'Auto', DENON_API_Commands::ECOAUTO],
                    [2, 'On', DENON_API_Commands::ECOON],
                ],
            ],
            self::ptDimmer            => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::DIM, 'Name' => 'Dimmer',
                'PropertyName'                               => 'Dimmer',
                'Profilesettings'                            => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                               => [
                    [0, 'Off', DENON_API_Commands::DIMOFF],
                    [1, 'Dark', DENON_API_Commands::DIMDAR],
                    [2, 'Dim', DENON_API_Commands::DIMDIM],
                    [3, 'Bright', DENON_API_Commands::DIMBRI],
                ],
            ],
            self::ptDynamicVolume => ['Type'                        => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSDYNVOL, 'Name' => 'Dynamic Volume',
                'PropertyName'                                      => 'DynamicVolume',
                'Profilesettings'                                   => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                                      => [
                    [0, 'Off', DENON_API_Commands::DYNVOLOFF],
                    [1, 'Light', DENON_API_Commands::DYNVOLLIT],
                    [2, 'Medium', DENON_API_Commands::DYNVOLMED],
                    [3, 'Heavy', DENON_API_Commands::DYNVOLHEV],
                    [4, 'Day', DENON_API_Commands::DYNVOLDAY],    // only older AVRs
                    [5, 'Evening', DENON_API_Commands::DYNVOLEVE], // only older AVRs
                    [6, 'Midnight', DENON_API_Commands::DYNVOLNGT], // only older AVRs
                    [7, 'Midnight', DENON_API_Commands::DYNVOLON], // only older Denon AVRs (i.e. 4310)
                ],
            ],
            self::ptSurroundLevelCompensation => ['Type'   => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSSURLEV, 'Name' => 'Surround Level Compensation',
                'PropertyName'                             => 'SurroundLevelCompensation',
                'Profilesettings'                          => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                             => [
                    [0, 'Off', DENON_API_Commands::PSSURLEVOFF],
                    [1, 'Light', DENON_API_Commands::PSSURLEVLIT],
                    [2, 'Medium', DENON_API_Commands::PSSURLEVMED],
                    [3, 'Heavy', DENON_API_Commands::PSSURLEVHEV],
                ],
            ],
            self::ptChannelExpander => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSCEX, 'Name' => 'Channel Expander',
                'PropertyName'                             => 'ChannelExpander',
                'Profilesettings'                          => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                             => [
                    [0, 'Off', DENON_API_Commands::PSCEXOFF],
                    [1, 'Low', DENON_API_Commands::PSCEXLOW],
                    [2, 'High', DENON_API_Commands::PSCEXHI],
                ],
            ],
            self::ptDACFilter => ['Type'                   => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSDACFIL, 'Name' => 'DAC Filter',
                'PropertyName'                             => 'DACFilter',
                'Profilesettings'                          => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                             => [
                    [0, 'Mode 1', DENON_API_Commands::PSDACFILMODE1],
                    [1, 'Mode 2', DENON_API_Commands::PSDACFILMODE2],
                ],
            ],
            self::ptResolutionHDMI => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::VSSCH, 'Name' => 'Resolution HDMI',
                'PropertyName'                            => 'ResolutionHDMI',
                'Profilesettings'                         => ['TV', '', '', 0, 0, 0, 0],
                'Associations'                            => [
                    [0, '480p/576p', DENON_API_Commands::SCH48P],
                    [1, '1080i', DENON_API_Commands::SCH10I],
                    [2, '720p', DENON_API_Commands::SCH72P],
                    [3, '1080p', DENON_API_Commands::SCH10P],
                    [4, '1080p 24Hz', DENON_API_Commands::SCH10P24],
                    [5, '4K', DENON_API_Commands::SCH4K],
                    [6, '4K(60/50)', DENON_API_Commands::SCH4KF],
                    [7, '8K', DENON_API_Commands::SCH8K],
                    [8, 'Auto', DENON_API_Commands::SCHAUTO],
                    [9, 'Off', DENON_API_Commands::SCHOFF],
                ],
            ],
            self::ptResolution => ['Type'                        => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::VSSC, 'Name' => 'Resolution',
                'PropertyName'                                   => 'Resolution',
                'Profilesettings'                                => ['TV', '', '', 0, 0, 0, 0],
                'Associations'                                   => [
                    [0, '480p/576p', DENON_API_Commands::SC48P],
                    [1, '1080i', DENON_API_Commands::SC10I],
                    [2, '720p', DENON_API_Commands::SC72P],
                    [3, '1080p', DENON_API_Commands::SC10P],
                    [4, '1080p 24Hz', DENON_API_Commands::SC10P24],
                    [5, '4K', DENON_API_Commands::SC4K],
                    [6, '4K(60/50)', DENON_API_Commands::SC4KF],
                    [7, '8K', DENON_API_Commands::SC8K],
                    [8, 'Auto', DENON_API_Commands::SCAUTO],
                ],
            ],
            self::ptDimension => ['Type'                        => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::PSDIM, 'Name' => 'Dimension',
                'PropertyName'                                  => 'Dimension',
                'Profilesettings'                               => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                                  => [
                    [0, '0', ' 00'],
                    [1, '1', ' 01'],
                    [2, '2', ' 02'],
                    [3, '3', ' 03'],
                    [4, '4', ' 04'],
                    [5, '5', ' 05'],
                    [6, '6', ' 06'],
                ],
            ],
            self::ptSleep => ['Type'                            => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::SLP, 'Name' => 'Sleep',
                'PropertyName'                                  => 'Sleep',
                'Profilesettings'                               => ['Clock', '', '', 0, 0, 0, 0],
                'Associations'                                  => [
                    [0, 'Off', 'OFF'],
                    [1, '10 min', '010'],
                    [2, '20 min', '020'],
                    [3, '30 min', '030'],
                    [4, '40 min', '040'],
                    [5, '50 min', '050'],
                    [6, '60 min', '060'],
                    [7, '70 min', '070'],
                    [8, '80 min', '080'],
                    [9, '90 min', '090'],
                    [10, '100 min', '100'],
                    [11, '110 min', '110'],
                    [12, '120 min', '120'],
                ],
                'IndividualStatusRequest' => 'SLP?',
            ],
            self::ptZone2ChannelSetting => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::Z2CS, 'Name' => 'Zone 2 Channel Setting',
                'PropertyName'                                 => 'Z2Channel',
                'Profilesettings'                              => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                                 => [
                    [0, 'Stereo', DENON_API_Commands::Z2CSST],
                    [1, 'Mono', DENON_API_Commands::Z2CSMONO],
                ],
            ],
            self::ptZone3ChannelSetting => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::Z3CS, 'Name' => 'Zone 3 Channel Setting',
                'PropertyName'                                 => 'Z3Channel',
                'Profilesettings'                              => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                                 => [
                    [0, 'Stereo', DENON_API_Commands::Z3CSST],
                    [1, 'Mono', DENON_API_Commands::Z3CSMONO],
                ],
            ],
            self::ptZone2QuickSelect => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::Z2QUICK, 'Name' => 'Zone 2 Quick Select',
                'PropertyName'                              => 'Z2Quick',
                'Profilesettings'                           => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                              => [
                    [0, '-', DENON_API_Commands::MSQUICK0],
                    [1, 'Select 1', DENON_API_Commands::MSQUICK1],
                    [2, 'Select 2', DENON_API_Commands::MSQUICK2],
                    [3, 'Select 3', DENON_API_Commands::MSQUICK3],
                    [4, 'Select 4', DENON_API_Commands::MSQUICK4],
                    [5, 'Select 5', DENON_API_Commands::MSQUICK5],
                    [6, 'Select 6', DENON_API_Commands::MSQUICK6], // erst ab CY2026, wird über Z2QUICK_SubCommands gefiltert
                ],
            ],
            // Zone 3 kennt laut CY2026-Spec kein Quick Select 6, deshalb bleibt es hier bei 0-5
            self::ptZone3QuickSelect => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::Z3QUICK, 'Name' => 'Zone 3 Quick Select',
                'PropertyName'                              => 'Z3Quick',
                'Profilesettings'                           => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                              => [
                    [0, '-', DENON_API_Commands::MSQUICK0],
                    [1, 'Select 1', DENON_API_Commands::MSQUICK1],
                    [2, 'Select 2', DENON_API_Commands::MSQUICK2],
                    [3, 'Select 3', DENON_API_Commands::MSQUICK3],
                    [4, 'Select 4', DENON_API_Commands::MSQUICK4],
                    [5, 'Select 5', DENON_API_Commands::MSQUICK5],
                ],
            ],
            self::ptZone2AutoStandbySetting => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::Z2STBY, 'Name' => 'Zone 2 Auto Standby',
                'PropertyName'                                     => 'ZONE2AutoStandbySetting',
                'Profilesettings'                                  => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                                     => [
                    [0, 'Off', DENON_API_Commands::Z2STBYOFF],
                    [1, '2 h', DENON_API_Commands::Z2STBY2H],
                    [2, '4 h', DENON_API_Commands::Z2STBY4H],
                    [3, '8 h', DENON_API_Commands::Z2STBY8H],
                ],
            ],
            self::ptZone3AutoStandbySetting => ['Type'                        => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::Z3STBY, 'Name' => 'Zone 3 Auto Standby',
                'PropertyName'                                                => 'ZONE3AutoStandbySetting',
                'Profilesettings'                                             => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                                                => [
                    [0, 'Off', DENON_API_Commands::Z3STBYOFF],
                    [1, '2 h', DENON_API_Commands::Z3STBY2H],
                    [2, '4 h', DENON_API_Commands::Z3STBY4H],
                    [3, '8 h', DENON_API_Commands::Z3STBY8H],
                ],
            ],
            self::ptZone2HDMIAudio => ['Type'                                 => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::Z2HDA, 'Name' => 'Zone 2 HDMI Audio',
                'PropertyName'                                                => 'Zone2HDMIAudio',
                'Profilesettings'                                             => ['Intensity', '', '', 0, 0, 0, 0],
                'Associations'                                                => [
                    [0, 'Pass-Through', DENON_API_Commands::Z2HDATHR],
                    [1, 'PCM', DENON_API_Commands::Z2HDAPCM],
                ],
            ],

            self::ptTunerAnalogPreset => ['Type'                    => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::TPAN, 'Name' => 'Tuner Preset',
                                          'PropertyName'            => 'TunerPreset',
                                          'Profilesettings'         => ['Database', '', '', 0, 0, 0, 0],
                                          'Associations'            => $assRange00to56,
                                          'IndividualStatusRequest' => 'TPAN?',
            ],


            //--- Attention: the order of the next two items may not be changed, becauseTM is a substring of TMAN
            self::ptTunerAnalogBand => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::TMAN_BAND, 'Name' => 'Tuner Band',
                'PropertyName'                                 => 'TunerBand',
                'Profilesettings'                              => ['Database', '', '', 0, 0, 0, 0],
                'Associations'                                 => [
                    [0, 'AM', DENON_API_Commands::TMANAM],
                    [1, 'FM', DENON_API_Commands::TMANFM],
                    [2, 'DAB', DENON_API_Commands::TMANDAB],
                ],
                'IndividualStatusRequest' => 'TMAN?',
            ],

            self::ptTunerAnalogMode => ['Type'             => DENONIPSVarType::vtInteger, 'Ident' => DENON_API_Commands::TMAN_MODE, 'Name' => 'Tuner Mode',
                                        'PropertyName'                                 => 'TunerMode',
                                        'Profilesettings'                              => ['Database', '', '', 0, 0, 0, 0],
                                        'Associations'                                 => [
                                            [0, 'automatisch', DENON_API_Commands::TMANAUTO],
                                            [1, 'manuell', DENON_API_Commands::TMANMANUAL],
                                        ],
                                        'IndividualStatusRequest' => 'TMAN?',
            ],

            //Type Float
            //           DENONIPSProfiles::ptDimension => ["Type" => DENONIPSVarType::vtFloat, "Ident" => DENON_API_Commands::PSDIM, "Name" => "Dimension",
            //                                             "PropertyName" => "Dimension", "Profilesettings" => ["Intensity", "", " dB", 0, 6, 1, 0], "Associations" => $assRange00to06],
            self::ptDialogControl => ['Type'                                  => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSDIC, 'Name' => 'Dialog Control',
                'PropertyName'                                                => 'DialogControl', 'Profilesettings' => ['Intensity', '', ' dB', 0, 6, 1, 0], 'Associations' => $assRange00to06, ],
            self::ptMasterVolume => ['Type'                                   => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::MV, 'Name' => 'Master Volume',
                'PropertyName'                                                => self::ptMasterVolume, 'Profilesettings' => ['Intensity', '', ' dB', -80.0, 18.0, 0.5, 1], 'Associations' => $assRange00to98_add05step,
                'IndividualStatusRequest'                                     => 'MV?', ],
            self::ptChannelVolumeFL => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVFL, 'Name' => 'Channel Volume Front Left',
                'PropertyName'                                                => 'FL', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeFR => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVFR, 'Name' => 'Channel Volume Front Right',
                'PropertyName'                                                => 'FR', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeC => ['Type'                                 => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVC, 'Name' => 'Channel Volume Center',
                'PropertyName'                                                => 'C', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeSW => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVSW, 'Name' => 'Channel Volume Subwoofer',
                'PropertyName'                                                => 'SW', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeSW2 => ['Type'                               => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVSW2, 'Name' => 'Channel Volume Subwoofer 2',
                'PropertyName'                                                => 'SW2', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeSW3 => ['Type'                               => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVSW3, 'Name' => 'Channel Volume Subwoofer 3',
                'PropertyName'                                                => 'SW3', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeSW4 => ['Type'                               => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVSW4, 'Name' => 'Channel Volume Subwoofer 4',
                'PropertyName'                                                => 'SW4', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeSL => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVSL, 'Name' => 'Channel Volume Surround Left',
                'PropertyName'                                                => 'SL', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeSR => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVSR, 'Name' => 'Channel Volume Surround Right',
                'PropertyName'                                                => 'SR', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeSBL => ['Type'                               => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVSBL, 'Name' => 'Channel Volume Surround Back Left',
                'PropertyName'                                                => 'SBL', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeSBR => ['Type'                               => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVSBR, 'Name' => 'Channel Volume Surround Back Right',
                'PropertyName'                                                => 'SBR', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeSB => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVSB, 'Name' => 'Channel Volume Surround Back',
                'PropertyName'                                                => 'SB', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeFHL => ['Type'                               => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVFHL, 'Name' => 'Channel Volume Front Height Left',
                'PropertyName'                                                => 'FHL', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeFHR => ['Type'                               => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVFHR, 'Name' => 'Channel Volume Front Height Right',
                'PropertyName'                                                => 'FHR', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeFWL => ['Type'                               => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVFWL, 'Name' => 'Channel Volume Front Wide Left',
                'PropertyName'                                                => 'FWL', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptChannelVolumeFWR => ['Type'                               => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVFWR, 'Name' => 'Channel Volume Front Wide Right',
                'PropertyName'                                                => 'FWR', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptSurroundHeightLch => ['Type'                              => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVSHL, 'Name' => 'Surround Height Left',
                'PropertyName'                                                => 'SurroundHeightLch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptSurroundHeightRch => ['Type'                              => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVSHR, 'Name' => 'Surround Height Right',
                'PropertyName'                                                => 'SurroundHeightRch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptTopSurround => ['Type'                                    => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVTS, 'Name' => 'Top Surround',
                'PropertyName'                                                => 'TopSurround', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptCenterHeight => ['Type'                                   => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVCH, 'Name' => 'Center Height',
                'PropertyName'                                                => 'CenterHeight', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptTactileTransducer => ['Type'                              => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVTTR, 'Name' => 'Tactile Transducer',
                'PropertyName'                                                => 'TactileTransducer', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptTopFrontLch => ['Type'                                    => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVTFL, 'Name' => 'Channel Volume Top Front Left',
                'PropertyName'                                                => 'TopFrontLch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptTopFrontRch => ['Type'                                    => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVTFR, 'Name' => 'Channel Volume Top Front Right',
                'PropertyName'                                                => 'TopFrontRch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptTopMiddleLch => ['Type'                                   => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVTML, 'Name' => 'Channel Volume Top Middle Left',
                'PropertyName'                                                => 'TopMiddleLch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptTopMiddleRch => ['Type'                                   => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVTMR, 'Name' => 'Channel Volume Top Middle Right',
                'PropertyName'                                                => 'TopMiddleRch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptTopRearLch => ['Type'                                     => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVTRL, 'Name' => 'Channel Volume Top Rear Left',
                'PropertyName'                                                => 'TopRearLch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptTopRearRch => ['Type'                                     => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVTRR, 'Name' => 'Channel Volume Top Rear Right',
                'PropertyName'                                                => 'TopRearRch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptRearHeightLch => ['Type'                                  => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVRHL, 'Name' => 'Channel Volume Rear Height Left',
                'PropertyName'                                                => 'RearHeightLch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptRearHeightRch => ['Type'                                  => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVRHR, 'Name' => 'Channel Volume Rear Height Right',
                'PropertyName'                                                => 'RearHeightRch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptFrontDolbyLch => ['Type'                                  => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVFDL, 'Name' => 'Channel Volume Front Dolby Left',
                'PropertyName'                                                => 'FrontDolbyLch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptFrontDolbyRch => ['Type'                                  => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVFDR, 'Name' => 'Channel Volume Front Dolby Right',
                'PropertyName'                                                => 'FrontDolbyRch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptSurroundDolbyLch => ['Type'                               => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVSDL, 'Name' => 'Channel Volume Surround Dolby Left',
                'PropertyName'                                                => 'SurroundDolbyLch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptSurroundDolbyRch => ['Type'                               => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVSDR, 'Name' => 'Channel Volume Surround Dolby Right',
                'PropertyName'                                                => 'SurroundDolbyRch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptBackDolbyLch => ['Type'                                   => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVBDL, 'Name' => 'Channel Volume Back Dolby Left',
                'PropertyName'                                                => 'BackDolbyLch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],
            self::ptBackDolbyRch => ['Type'                                   => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::CVBDR, 'Name' => 'Channel Volume Back Dolby Right',
                'PropertyName'                                                => 'BackDolbyRch', 'Profilesettings' => ['Intensity',  '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step,
                'IndividualStatusRequest'                                     => 'CV?', ],

            //--- Attention: the order of the next two items may not be changed, because PSDEL is a substring of PSDELAY
            self::ptAudioDelay => ['Type'                     => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSDELAY, 'Name' => 'Audio Delay',
                'PropertyName'                                => 'AudioDelay', 'Profilesettings' => ['Intensity', '', ' ms', 0, 200, 1, 0], 'Associations' => $assRange000to200, ],
            self::ptDelay => ['Type'                          => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSDEL, 'Name' => 'Delay',
                'PropertyName'                                => 'Delay', 'Profilesettings' => ['Intensity', '', ' ms', 0, 300, 1, 0], 'Associations' => $assRange000to300, ],
            //---
            self::ptCenterLevelAdjust => ['Type'                          => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSCLV, 'Name' => 'Center Level Adjust',
                'PropertyName'                                            => 'CenterLevelAdjust', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 1, 0], 'Associations' => $assRange38to62],
            self::ptLFELevel => ['Type'                                   => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSLFE, 'Name' => 'LFE Level',
                'PropertyName'                                            => 'LFELevel', 'Profilesettings' => ['Intensity', '', ' dB', -10.0, 0.0, 1, 0], 'Associations' => $assRange00to10_invert, ],
            self::ptLFE71Level => ['Type'                                 => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSLFL, 'Name' => 'LFE 7.1 Level',
                'PropertyName'                                            => 'LFE71Level', 'Profilesettings' => ['Intensity', '', ' dB', -15.0, 0.0, 1, 0], 'Associations' => $assRange00to15_invert, ],
            self::ptBassLevel => ['Type'                                  => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSBAS, 'Name' => 'Bass Level',
                'PropertyName'                                            => 'BassLevel', 'Profilesettings' => ['Intensity', '', ' dB', -6, 6, 1, 0], 'Associations' => $assRange44to56, ],
            self::ptTrebleLevel => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSTRE, 'Name' => 'Treble Level',
                'PropertyName'                                            => 'TrebleLevel', 'Profilesettings' => ['Intensity', '', ' dB', -6, 6, 1, 0], 'Associations' => $assRange44to56, ],
            self::ptCenterWidth => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSCEN, 'Name' => 'Center Width',
                'PropertyName'                                            => 'CenterWidth', 'Profilesettings' => ['Intensity',  '', ' dB', 0, 7, 1, 0], 'Associations' => $assRange00to07, ],
            self::ptEffectLevel => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSEFF, 'Name' => 'Effect Level',
                'PropertyName'                                            => 'EffectLevel', 'Profilesettings' => ['Intensity', '', ' dB', 0, 15, 1, 0], 'Associations' => $assRange00to15, ],
            self::ptCenterImage => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSCEI, 'Name' => 'Center Image',
                'PropertyName'                                            => 'CenterImage', 'Profilesettings' => ['Intensity', '', ' dB', 0.0, 1.0, 0.1, 1], 'Associations' => $assRange00to10_stepwide_01, ],
            self::ptCenterGain => ['Type'                                 => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSCEG, 'Name' => 'Center Gain',
                'PropertyName'                                            => 'CenterGain', 'Profilesettings' => ['Intensity', '', ' dB', 0.0, 1.0, 0.1, 1], 'Associations' => $assRange00to10_stepwide_01, ],
            self::ptContrast => ['Type'                                   => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PVCN, 'Name' => 'Contrast',
                'PropertyName'                                            => 'Contrast', 'Profilesettings' => ['Intensity', '', ' dB', -6, 6, 1, 0], 'Associations' => $assRange44to56, ],
            self::ptBrightness => ['Type'                                 => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PVBR, 'Name' => 'Brightness',
                'PropertyName'                                            => 'Brightness', 'Profilesettings' => ['Intensity', '', ' dB', 0, 12, 1, 0], 'Associations' => $assRange00to12, ],
            self::ptSaturation => ['Type'                                 => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PVST, 'Name' => 'Saturation',
                'PropertyName'                                            => 'Saturation', 'Profilesettings' => ['Intensity', '', ' dB', -6, 6, 1, 0], 'Associations' => $assRange44to56, ],
            self::ptChromalevel => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PVCM, 'Name' => 'Chroma Level',
                'PropertyName'                                            => 'Chromalevel', 'Profilesettings' => ['Intensity', '', ' dB', -6, 6, 1, 0], 'Associations' => $assRange44to56, ],
            self::ptHue => ['Type'                                        => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PVHUE, 'Name' => 'Hue',
                'PropertyName'                                            => 'Hue', 'Profilesettings' => ['Intensity', '', ' dB', -6, 6, 1, 0], 'Associations' => $assRange44to56, ],
            self::ptEnhancer => ['Type'                                   => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PVENH, 'Name' => 'Enhancer',
                'PropertyName'                                            => 'Enhancer', 'Profilesettings' => ['Intensity', '', ' dB', 0, 12, 1, 0], 'Associations' => $assRange00to12, ],
            self::ptStageHeight => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSSTH, 'Name' => 'Stage Height',
                'PropertyName'                                            => 'StageHeight', 'Profilesettings' => ['Intensity', '', ' dB', -10, 10, 1, 0], 'Associations' => $assRange40to60, ],
            self::ptStageWidth => ['Type'                                 => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSSTW, 'Name' => 'Stage Width',
                'PropertyName'                                            => 'StageWidth', 'Profilesettings' => ['Intensity', '', ' dB', -10, 10, 1, 0], 'Associations' => $assRange40to60, ],
            self::ptAudysseyContainmentAmount => ['Type'                  => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSCNTAMT, 'Name' => 'Audyssey Containment Amount',
                'PropertyName'                                            => 'AudysseyContainmentAmount', 'Profilesettings' => ['Intensity',  '', '', 1, 7, 1, 0], 'Associations' => $assRange00to07, ],
            self::ptBassSync => ['Type'                                   => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSBSC, 'Name' => 'BassSync',
                'PropertyName'                                            => 'BassSync', 'Profilesettings' => ['Intensity', '', ' dB', 0, 16, 1, 0], 'Associations' => $assRange00to16, ],
            self::ptSubwooferLevel => ['Type'                             => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSSWL, 'Name' => 'Subwoofer Level',
                'PropertyName'                                            => 'SubwooferLevel', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step, ],
            self::ptSubwoofer2Level => ['Type'                            => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSSWL2, 'Name' => 'Subwoofer 2 Level',
                'PropertyName'                                            => 'Subwoofer2Level', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step, ],
            self::ptSubwoofer3Level => ['Type'                            => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSSWL3, 'Name' => 'Subwoofer 3 Level',
                'PropertyName'                                            => 'Subwoofer3Level', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step, ],
            self::ptSubwoofer4Level => ['Type'                            => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSSWL4, 'Name' => 'Subwoofer 4 Level',
                'PropertyName'                                            => 'Subwoofer4Level', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step, ],
            self::ptDialogLevelAdjust => ['Type'                          => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSDIL, 'Name' => 'Dialog Level Adjust',
                'PropertyName'                                            => 'DialogLevelAdjust', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step, ],
            self::ptAuroMatic3DStrength => ['Type'                        => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::PSAUROST, 'Name' => 'Auromatic 3D Strength',
                'PropertyName'                                            => 'AuroMatic3DStrength', 'Profilesettings' => ['Intensity', '', ' dB', 0, 16, 1, 0], 'Associations' => $assRange00to16, ],
            self::ptBluetoothLevel => ['Type'                             => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::BTLEV, 'Name' => 'Bluetooth Level',
                'PropertyName'                                            => 'BluetoothLevel', 'Profilesettings' => ['Intensity', '', ' dB', -50, 10, 1, 0], 'Associations' => $assRange30to90, ],
            self::ptZone2Volume => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z2VOL, 'Name' => 'Zone 2 Volume',
                'PropertyName'                                            => self::ptZone2Volume, 'Profilesettings' => ['Intensity', '', ' dB', -80, 18, 1, 0], 'Associations' => $assRange00to98,
                'IndividualStatusRequest'                                 => 'Z2?', ],
            self::ptZone3Volume => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z3VOL, 'Name' => 'Zone 3 Volume',
                'PropertyName'                                            => self::ptZone3Volume, 'Profilesettings' => ['Intensity', '', ' dB', -80, 18, 1, 0], 'Associations' => $assRange00to98,
                'IndividualStatusRequest'                                 => 'Z3?', ],
            self::ptZone2Sleep => ['Type'                                 => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z2SLP, 'Name' => 'Zone 2 Sleep',
                'PropertyName'                                            => 'Z2Sleep', 'Profilesettings' => ['Clock', '', ' Min', 0, 120, 10, 0], 'Associations' => $assRange000to120_ptSleep, ],
            self::ptZone3Sleep => ['Type'                                 => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z3SLP, 'Name' => 'Zone 3 Sleep',
                'PropertyName'                                            => 'Z3Sleep', 'Profilesettings' => ['Clock', '', ' Min', 0, 120, 10, 0], 'Associations' => $assRange000to120_ptSleep, ],
            self::ptZone2ChannelVolumeFL => ['Type'                       => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z2CVFL, 'Name' => 'Zone 2 Channel Volume Front Left',
                'PropertyName'                                            => 'Z2CVFL', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step, ],
            self::ptZone2ChannelVolumeFR => ['Type'                       => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z2CVFR, 'Name' => 'Zone 2 Channel Volume Front Right',
                'PropertyName'                                            => 'Z2CVFR', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step, ],
            self::ptZone3ChannelVolumeFL => ['Type'                       => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z3CVFL, 'Name' => 'Zone 3 Channel Volume Front Left',
                'PropertyName'                                            => 'Z3CVFL', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step, ],
            self::ptZone3ChannelVolumeFR => ['Type'                       => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z3CVFR, 'Name' => 'Zone 3 Channel Volume Front Right',
                'PropertyName'                                            => 'Z3CVFR', 'Profilesettings' => ['Intensity', '', ' dB', -12, 12, 0.5, 1], 'Associations' => $assRange38to62_add05step, ],
            self::ptZone2Bass => ['Type'                                  => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z2PSBAS, 'Name' => 'Zone 2 Bass',
                'PropertyName'                                            => 'Z2Bass', 'Profilesettings' => ['Intensity', '', ' dB', -10, 10, 1, 0], 'Associations' => $assRange40to60, ],
            self::ptZone3Bass => ['Type'                                  => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z3PSBAS, 'Name' => 'Zone 3 Bass',
                'PropertyName'                                            => 'Z3Bass', 'Profilesettings' => ['Intensity', '', ' dB', -10, 10, 1, 0], 'Associations' => $assRange40to60, ],
            self::ptZone2Treble => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z2PSTRE, 'Name' => 'Zone 2 Treble',
                'PropertyName'                                            => 'Z2Treble', 'Profilesettings' => ['Intensity', '', ' dB', -10, 10, 1, 0], 'Associations' => $assRange40to60, ],
            self::ptZone3Treble => ['Type'                                => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::Z3PSTRE, 'Name' => 'Zone 3 Treble',
                'PropertyName'                                            => 'Z3Treble', 'Profilesettings' => ['Intensity', '', ' dB', -10, 10, 1, 0], 'Associations' => $assRange40to60, ],
            self::ptSSINFAISFSV => ['Type' => DENONIPSVarType::vtFloat, 'Ident' => DENON_API_Commands::SSINFAISFSV, 'Name' => 'Audio: Abtastrate',
                              'PropertyName'                                        => 'SSINFAISFSV', 'Profilesettings' => ['Information', '', ' kHz', 0, 0, 0, 1], 'Associations' => [], 'displayOnly' => true],

            //Type String
            self::ptMainZoneName    => ['Type' => DENONIPSVarType::vtString, 'Ident' => 'MainZoneName', 'Name' => 'MainZone Name', 'PropertyName' => 'ZoneName', 'Profilesettings' => ['Information'], 'displayOnly' => true],
            self::ptModel           => ['Type' => DENONIPSVarType::vtString, 'Ident' => 'Model', 'Name' => 'Model', 'PropertyName' => 'Model', 'Profilesettings' => ['Information'], 'displayOnly' => true],
            self::ptSurroundDisplay => ['Type' => DENONIPSVarType::vtString, 'Ident' => DENON_API_Commands::SURROUNDDISPLAY, 'Name' => 'Surround Mode Display',
                                        'PropertyName'                                        => 'SurroundDisplay', 'Profilesettings' => ['Information'], 'displayOnly' => true ],
            self::ptSYSMI => ['Type' => DENONIPSVarType::vtString, 'Ident' => DENON_API_Commands::SYSMI, 'Name' => 'Audio: Soundmodus',
                                        'PropertyName'                                        => 'SYSMI', 'Profilesettings' => ['Information'], 'Associations' => [], 'displayOnly' => true],
            self::ptSYSDA => ['Type' => DENONIPSVarType::vtString, 'Ident' => DENON_API_Commands::SYSDA, 'Name' => 'Audio: Eingangssignal',
                                        'PropertyName'                                        => 'SYSDA', 'Profilesettings' => ['Information'], 'Associations' => [], 'displayOnly' => true],
            self::ptDisplay => ['Type'                                => DENONIPSVarType::vtString, 'Ident' => DENON_API_Commands::DISPLAY, 'Name' => 'OSD Info', 'ProfilName' => '~HTMLBox', 'PropertyName' => 'Display', 'Profilesettings' => ['TV'],
                'IndividualStatusRequest'                             => 'NSA', 'displayOnly' => true],
            self::ptZone2Name => ['Type' => DENONIPSVarType::vtString, 'Ident' => 'Zone2Name', 'Name' => 'Zone 2 Name', 'PropertyName' => self::ptZone2Name, 'Profilesettings' => ['Information'], 'displayOnly' => true],
            self::ptZone3Name => ['Type' => DENONIPSVarType::vtString, 'Ident' => 'Zone3Name', 'Name' => 'Zone 3 Name', 'PropertyName' => self::ptZone3Name, 'Profilesettings' => ['Information'], 'displayOnly' => true],
        ];

        if ($AVRType !== null) {
            $this->AVRType = $AVRType;

            // some profiles have to be adapted to the capabilities of the AVR
            $caps = AVRs::getCapabilities($AVRType);
            $this->updateProfileAccordingToCaps(self::ptSurroundMode, $caps);
            $this->updateProfileAccordingToCaps(self::ptResolution, $caps);
            $this->updateProfileAccordingToCaps(self::ptResolutionHDMI, $caps);
            $this->updateProfileAccordingToCaps(self::ptSpeakerOutput, $caps);
            $this->updateProfileAccordingToCaps(self::ptDynamicVolume, $caps);
            $this->updateProfileAccordingToCaps(self::ptVideoSelect, $caps);
            $this->updateProfileAccordingToCaps(self::ptQuickSelect, $caps);
            $this->updateProfileAccordingToCaps(self::ptZone2QuickSelect, $caps);

            if (in_array($AVRType, ['AVR-X4000', 'AVR_3808A', 'AVR-X3000', 'AVR-4310', 'AVR-4311', 'AVR-3310', 'AVR-3311', 'AVR-3312', 'AVR-3313',
                                    'Marantz-SR6005', 'Marantz-SR6006', 'Marantz-NR1602', 'Marantz-SR5006', 'Marantz-SR7005', 'Marantz-AV7005'])){
                $this->profiles[self::ptTunerAnalogPreset]['Associations'] = $assRangeA1toG8;
            }

            if (in_array($AVRType, ['DRA-N5', 'RCD-N8'])) {
                $this->profiles[self::ptMasterVolume] = [
                    'Type'                    => DENONIPSVarType::vtFloat,
                    'Ident'                   => DENON_API_Commands::MV,
                    'Name'                    => 'Master Volume',
                    'PropertyName'            => self::ptMasterVolume,
                    'Profilesettings'         => ['Intensity', '', '', 0, 60, 1, 0],
                    'Associations'            => $this->GetAssociationOfAsciiTodB('00', '60', '00', 1, false, false),
                    'IndividualStatusRequest' => 'MV?', ];

                $this->profiles[self::ptBassLevel]    = [
                    'Type'            => DENONIPSVarType::vtFloat,
                    'Ident'           => DENON_API_Commands::PSBAS,
                    'Name'            => 'Bass Level',
                    'PropertyName'    => self::ptBassLevel,
                    'Profilesettings' => ['Intensity', '', ' dB', -10, 10, 2, 0],
                    'Associations'    => $this->GetAssociationOfAsciiTodB('40', '60', '50', 2)];

                $this->profiles[self::ptTrebleLevel] = [
                    'Type'            => DENONIPSVarType::vtFloat,
                    'Ident'           => DENON_API_Commands::PSTRE,
                    'Name'            => 'Treble Level',
                    'PropertyName'    => self::ptTrebleLevel,
                    'Profilesettings' => ['Intensity', '', ' dB', -10, 10, 2, 0],
                    'Associations'    => $this->GetAssociationOfAsciiTodB('40', '60', '50', 2)];

            }
        }

        if ($InputMapping !== null) {
            $associations = [];
            foreach ($InputMapping as $key=> $value) {
                $associations[] = [$value, '', $key];
            }
            $associations[] = [count($associations), 'Source', 'SOURCE'];
            $this->profiles[self::ptInputSource]['Associations'] = $associations;
            $this->profiles[self::ptZone2InputSource]['Associations'] = $associations;
            $this->profiles[self::ptZone3InputSource]['Associations'] = $associations;
            if ($this->debug) {
                call_user_func($this->Logger_Dbg, __CLASS__ . '::' . __FUNCTION__, 'Association: ' . json_encode($associations, JSON_THROW_ON_ERROR));
            }
        }
    }

    private function updateProfileAccordingToCaps($profilename, $caps): void
    {
        $ident = $this->profiles[$profilename]['Ident'];
        $associations = $this->profiles[$profilename]['Associations'];
        if (!array_key_exists($ident . '_SubCommands', $caps)) {
            trigger_error(__FUNCTION__ . ': unknown capability "' . $ident . '_SubCommands' . '"');

            return; //ohne Rückkehr liefe in_array() gegen null und würde fatal
        }
        $subcommands = $caps[$ident . '_SubCommands'];
        for ($i = (count($associations) - 1); $i >= 0; $i--) {
            if (!in_array($associations[$i][2], $subcommands, true)) {
                unset($associations[$i]);
            }
        }
        $this->profiles[$profilename]['Associations'] = array_values($associations);
    }

    public function SetInputSources($DenonIP, $Zone, $FAVORITES, $IRADIO, $SERVER, $NAPSTER, $LASTFM, $FLICKR): void
    {
        if ($this->debug) {
            call_user_func(
                $this->Logger_Dbg,
                __CLASS__ . '::' . __FUNCTION__,
                sprintf(
                    'Parameters - IP: %s, Zone: %s, Favorites: %s, IRadio: %s, Server: %s, Napster: %s, LastFM: %s, Flickr: %s',
                    $DenonIP,
                    $Zone,
                    (int)$FAVORITES,
                    (int)$IRADIO,
                    (int)$SERVER,
                    (int)$NAPSTER,
                    (int)$LASTFM,
                    (int)$FLICKR
                )
            );
        }

        $caps = AVRs::getCapabilities($this->AVRType);
        if ($caps['httpMainZone'] !== DENON_HTTP_Interface::NoHTTPInterface) {
            if (!filter_var($DenonIP, FILTER_VALIDATE_IP)) {
                trigger_error(__FUNCTION__ . ': Die IP Adresse "' . $DenonIP . '" ist ungültig!');

                return;
            }
            $Associations = $this->GetAssociationsOfInputSourcesAccordingToHTTPInfo(
                $DenonIP,
                $caps['httpMainZone'],
                $Zone
            );

            if ($Associations === null) {

                return;
            }

        } else {
            //Assoziationen aufbauen
            $Associations = [];
            foreach ($caps['SI_SubCommands'] as $key=>$subcommand){
                $Associations[] = [$key, $subcommand, $subcommand];
            }
        }

        //zusätzliche Auswahl 'SOURCE' bei Zonen
        if ($Zone > 0) {
            $Associations[] = [count($Associations), 'SOURCE', DENON_API_Commands::IS_SOURCE];
        }

        //zusätzliche Inputs bei Auswahl
        if ($FAVORITES && (!in_array(DENON_API_Commands::IS_FAVORITES, $caps['SI_SubCommands'], true))) {
            $Associations[] = [count($Associations), 'Favoriten', DENON_API_Commands::IS_FAVORITES];
        }
        if ($IRADIO && (!in_array(DENON_API_Commands::IS_IRADIO, $caps['SI_SubCommands'], true))) {
            $Associations[] = [count($Associations), 'Internet Radio', DENON_API_Commands::IS_IRADIO];
        }
        if ($SERVER && (!in_array(DENON_API_Commands::IS_SERVER, $caps['SI_SubCommands'], true))) {
            $Associations[] = [count($Associations), 'Server', DENON_API_Commands::IS_SERVER];
        }
        if ($NAPSTER && (!in_array(DENON_API_Commands::IS_LASTFM, $caps['SI_SubCommands'], true))) {
            $Associations[] = [count($Associations), 'Napster', DENON_API_Commands::IS_NAPSTER];
        }
        if ($LASTFM && (!in_array(DENON_API_Commands::IS_FAVORITES, $caps['SI_SubCommands'], true))) {
            $Associations[] = [count($Associations), 'LastFM', DENON_API_Commands::IS_LASTFM];
        }
        if ($FLICKR && (!in_array(DENON_API_Commands::IS_FLICKR, $caps['SI_SubCommands'], true))) {
            $Associations[] = [count($Associations), 'Flickr', DENON_API_Commands::IS_FLICKR];
        }

        if ($this->debug) {
            call_user_func($this->Logger_Dbg, __CLASS__ . '::' . __FUNCTION__, 'Associations: ' . json_encode($Associations, JSON_THROW_ON_ERROR));
        }

        switch ($Zone) {
            case 0:
                $this->profiles[self::ptInputSource]['Associations'] = $Associations;
                break;

            case 1:
                $this->profiles[self::ptZone2InputSource]['Associations'] = $Associations;
                break;

            case 2:
                $this->profiles[self::ptZone3InputSource]['Associations'] = $Associations;
                break;

            default:
                trigger_error('unknown zone: ' . $Zone);
       }

    }

    private function GetInputsFromXMLZone(SimpleXMLElement $xmlZone, $MainForm, $filename): ?array
    {
        //Inputs
        $InputFuncList = $xmlZone->xpath('.//InputFuncList');
        if (count($InputFuncList) === 0) {
            trigger_error('InputFuncList has no children: '
                . '(filename correct?: "' . $filename . '", content: '
                          . json_encode($xmlZone, JSON_THROW_ON_ERROR)
            );

            return null;
        }

        $RenameSource = $xmlZone->xpath('.//RenameSource');
        if (count($RenameSource) === 0) {
            trigger_error('RenameSource has no children: '
                . '(filename correct?: "' . $filename . '", content: '
                          . json_encode($xmlZone, JSON_THROW_ON_ERROR)
            );

            return null;
        }

        $SourceDelete = $xmlZone->xpath('.//SourceDelete');
        if (count($SourceDelete) === 0) {
            trigger_error('SourceDelete has no children: '
                . '(filename correct?: "' . $filename . '", content: '
                          . json_encode($xmlZone, JSON_THROW_ON_ERROR)
            );

            return null;
        }

        $Inputs = [];
        $UsedInput_i = -1;
        $countinput = count($InputFuncList[0]->value);

        for ($i = 0; $i <= $countinput - 1; $i++) {
            //manche AVRs(z.B. Marantz 7010 bei 'Online Music') liefern auch schon mal einen Leerstring anstelle von 'USE'
            if (((string) $SourceDelete[0]->value[$i] === 'USE') || ((string) $SourceDelete[0]->value[$i] === '')) {
                $UsedInput_i++;
                if ($MainForm === DENON_HTTP_Interface::MainForm_old) {
                    $RenameInput = (string) $RenameSource[0]->value[$i];
                } else {
                    $RenameInput = (string) $RenameSource[0]->value[$i]->value;
                }

                if ($RenameInput !== '') {
                    $Inputs[$UsedInput_i] = ['Source' => (string) $InputFuncList[0]->value[$i], 'RenameSource' => $RenameInput];
                } else {
                    $Inputs[$UsedInput_i] = ['Source' => (string) $InputFuncList[0]->value[$i], 'RenameSource' => (string) $InputFuncList[0]->value[$i]];
                }
            }
        }

        //Assoziationen aufbauen
        $Associations = [];

        foreach ($Inputs as $Value => $Input) {
            // Beispiel: Association[] = [1, 'SONOS', 'CD']
            $Associations[] = [$Value, str_replace(' ', '', $Input['RenameSource']), str_replace(' ', '', $Input['Source'])];
        }

        return $Associations;
    }

    private function GetAssociationsOfInputSourcesAccordingToHTTPInfo($IP, $MainForm, $Zone): ?array
    {
        $filename = 'http://' . $IP . $MainForm . '?_=&ZoneName=ZONE' . ($Zone + 1);
        if ($this->debug) {
            call_user_func($this->Logger_Dbg, __CLASS__ . '::' . __FUNCTION__, 'filename: ' . $filename);
        }

        $content = @file_get_contents($filename);
        if ($content === false) {
            trigger_error('Datei ' . $filename . ' konnte nicht geöffnet werden.');

            return null;
        }

        $xmlZone = new SimpleXMLElement($content);
        if ($xmlZone->count() === 0) {
            trigger_error('xmlzone has no children. '
                . '(filename correct?: "' . $filename . '", content: '
                          . json_encode($xmlZone, JSON_THROW_ON_ERROR)
            );

            return null;
        }

        return $this->GetInputsFromXMLZone($xmlZone, $MainForm, $filename);

    }

    public function GetInputVarMapping($Zone): false|array
    {
        if ($Zone === 0) {
            $associations = $this->profiles[self::ptInputSource]['Associations'];
        } elseif ($Zone === 1) {
            $associations = $this->profiles[self::ptZone2InputSource]['Associations'];
        } elseif ($Zone === 2) {
            $associations = $this->profiles[self::ptZone3InputSource]['Associations'];
        } else {
            trigger_error('unknown zone: ' . $Zone);

            return false;
        }

        $InputSourcesMapping = [];
        foreach ($associations as $association) {
            $InputSourcesMapping[] = ['Source' => $association[2], 'RenameSource' => $association[1]];
        }

        $ret = ['AVRType' => $this->AVRType, 'Inputs' => $InputSourcesMapping];

        if ($this->debug) {
            call_user_func($this->Logger_Dbg, __CLASS__ . '::' . __FUNCTION__, 'return: ' . json_encode($ret, JSON_THROW_ON_ERROR));
        }

        return $ret;
    }

    public function GetVariableConfig($configId): false|array
    {
        if ($this->debug){
            call_user_func($this->Logger_Dbg, __CLASS__ . '::' . __FUNCTION__, 'Get variable config for id ' . $configId);
        }

        if (!array_key_exists($configId, $this->profiles)) {
            trigger_error('unknown ident: ' . $configId);

            return false;
        }

        $profile = $this->profiles[$configId];
        if (!isset($profile['Type'])) {
            trigger_error(__CLASS__ . '::' . __FUNCTION__ . ': Type not set in profile "' . $configId . '"');

            return false;
        }

        switch ($profile['Type']) {
            case DENONIPSVarType::vtBoolean:
                $ret = ['Name'     => $profile['Name'],
                        'Ident'        => $profile['Ident'],
                        'Type'         => $profile['Type'],
                        'PropertyName' => $profile['PropertyName'],
                        'Position'     => $this->getpos($configId),
                        'displayOnly'  => $profile['displayOnly'] ?? false
                ];
                break;

            case DENONIPSVarType::vtInteger:
            case DENONIPSVarType::vtFloat:
                $profilesettings = $profile['Profilesettings'];

                $ret = [
                    'Name'         => $profile['Name'],
                    'Ident'        => $profile['Ident'],
                    'Type'         => $profile['Type'],
                    'PropertyName' => $profile['PropertyName'],
                    'ProfilName'   => $configId,
                    'Icon'         => $profilesettings[0],
                    'Prefix'       => $profilesettings[1],
                    'Suffix'       => $profilesettings[2],
                    'MinValue'     => $profilesettings[3],
                    'MaxValue'     => $profilesettings[4],
                    'Stepsize'     => $profilesettings[5],
                    'Digits'       => $profilesettings[6],
                    'Associations' => $profile['Associations'],
                    'Position'     => $this->getpos($configId),
                    'displayOnly'  => $profile['displayOnly'] ?? false
                ];
                break;

            case DENONIPSVarType::vtString:
                $profilename=$profile['ProfilName'] ?? $configId;
                $ret        = [
                    'Name'         => $profile['Name'],
                    'Ident'        => $profile['Ident'],
                    'Type'         => $profile['Type'],
                    'PropertyName' => $profile['PropertyName'],
                    'ProfilName'   => $profilename,
                    'Position'     => $this->getpos($configId),
                    'Icon'         => $profile['Profilesettings'][0],
                    'displayOnly'  => $profile['displayOnly'] ?? false
                ];
                break;

            default:
                trigger_error('unknown profile type: ' . $profile['Type']);

                return false;

        }

        return $ret;
    }

    public function GetVariableProfileMapping(): array
    {
        $ret = [];

        foreach ($this->profiles as $profile) {
            if (!isset($profile['Associations'])) {
                continue;
            }

            $ValueMapping = [];
            foreach ($profile['Associations'] as $association) {
                try {
                    match ($profile['Type']) {
                        DENONIPSVarType::vtBoolean => $ValueMapping[$association[1]] = $association[0],
                        DENONIPSVarType::vtInteger => $ValueMapping[$association[2]] = $association[0],
                        DENONIPSVarType::vtFloat   => $ValueMapping[$association[0]] = $association[1],
                        DENONIPSVarType::vtString  => null, // Strings benötigen oft kein Mapping
                        default                    => throw new UnexpectedValueException('Unexpected type: ' . $profile['Type'])
                    };
                } catch (UnhandledMatchError|UnexpectedValueException $e) {
                    trigger_error(__FUNCTION__ . ': ' . $e->getMessage());
                }
            }

            $ret[$profile['Ident']] = [
                'VarType'      => $profile['Type'],
                'ValueMapping' => $ValueMapping
            ];
        }

        return $ret;
    }

    public function GetAllProfiles(): array
    {
        return $this->profiles;
    }

    public function GetAllProfilesSortedByPos(): array
    {
        $ret = [];
        $this->checkProfiles();

        foreach (static::$order as $profileID) {
            $ret[$profileID] = $this->profiles[$profileID];
        }

        return $ret;
    }

    private function checkProfiles(): bool
    {
        //check if all profiles have a position in $order
        $profile_without_pos = [];
        if (count(static::$order) !== count($this->profiles)) {
            foreach ($this->profiles as $profileID => $profile) {
                if (!in_array($profileID, static::$order, true)) {
                    $profile_without_pos[] = $profileID;
                }
            }
            if (count($profile_without_pos) > 0) {
                call_user_func($this->Logger_Dbg, __CLASS__ . '::' . __FUNCTION__, 'Order: ' . json_encode(static::$order, JSON_THROW_ON_ERROR));
                trigger_error(__CLASS__ . '::' . __FUNCTION__ . ': Profiles without positions: ' . json_encode(
                                  $profile_without_pos,
                                  JSON_THROW_ON_ERROR
                              )
                );

                return false;
            }
        }

        //check if all elements in order have a profile definition
        $order_without_definition = [];
        if (count(static::$order) !== count($this->profiles)) {
            foreach (static::$order as $order_item) {
                if (!array_key_exists($order_item, $this->profiles)) {
                    $order_without_definition[] = $order_item;
                }
            }
            if (count($order_without_definition) > 0) {
                call_user_func($this->Logger_Dbg,__CLASS__ . '::' . __FUNCTION__, 'Profiles: ' . json_encode($this->profiles, JSON_THROW_ON_ERROR));
                call_user_func($this->Logger_Dbg, __CLASS__ . '::' . __FUNCTION__, 'Keys: ' . json_encode(array_keys($this->profiles), JSON_THROW_ON_ERROR));
                trigger_error(__CLASS__ . '::' . __FUNCTION__ . ': Order Element without definition: ' . json_encode(
                                  $order_without_definition,
                                  JSON_THROW_ON_ERROR
                              )
                );

                return false;
            }
        }

        //check if all profiles are used in MAX Capabilities
        $all_capabilities = array_merge(
            AVR::$InfoFunctions_max,
            AVR::$AvrInfos_max,
            AVR::$PowerFunctions_max,
            AVR::$CV_Commands_max,
            AVR::$InputSettings_max,
            AVR::$PS_Commands_max,
            AVR::$PV_Commands_max,
            AVR::$SurroundMode_max,
            AVR::$VS_Commands_max,
            AVR::$SystemControl_Commands_max,
            AVR::$Zone_Commands_max,
            AVR::$Tuner_Control_max
        );

        //check if all profiles are at least used in Capabilities_max
        $profile_not_used_in_caps = [];
        foreach ($this->profiles as $profileID => $profile) {
            if (!in_array($profile['Ident'], $all_capabilities, true)) {
                $profile_not_used_in_caps[$profileID] = $profile['Ident'];
            }
        }

        if (count($profile_not_used_in_caps) > 0) {
            trigger_error(__CLASS__ . '::' . __FUNCTION__ . ': Profiles not used in Capabilities(MAX):' . json_encode(
                              $profile_not_used_in_caps,
                              JSON_THROW_ON_ERROR
                          ) . PHP_EOL . 'Capabilities: ' . json_encode($all_capabilities, JSON_THROW_ON_ERROR)
            );
            call_user_func($this->Logger_Dbg,__CLASS__ . '::' . __FUNCTION__, 'Profiles not used in Capabilities(MAX):' . json_encode(
                                                              $profile_not_used_in_caps,
                                                              JSON_THROW_ON_ERROR
                                                          ) . PHP_EOL . 'Capabilities: ' . json_encode($all_capabilities, JSON_THROW_ON_ERROR)
            );

            return false;
        }

        return true;
    }

    /**
     * Ermittelt den API-Subcommand für einen gegebenen Wert basierend auf dem Profil-Ident.
     *
     * Diese Methode sucht zuerst das passende Profil anhand von $Ident. Anschließend durchsucht
     * sie die Assoziationsliste dieses Profils, um den zum Wert ($Value) passenden
     * Befehl (Subcommand) zu finden.
     *
     * @param string $Ident Der Ident des Profils (z.B. "PW", "MV", "SI").
     * @param mixed  $Value Der Wert, nach dem gesucht werden soll (Typ abhängig vom Profil: bool, int, float).
     *
     * @return string|null Der gefundene Subcommand als String oder null, wenn nichts gefunden wurde.
     */
    public function GetSubCommandOfValue(string $Ident, $Value): ?string
    {
        // 1. Profil suchen
        $selectedProfile = null;
        foreach ($this->profiles as $profile) {
            if ($profile['Ident'] === $Ident) {
                $selectedProfile = $profile;
                break;
            }
        }

        // Guard Clause: Kein Profil gefunden
        if ($selectedProfile === null || !isset($selectedProfile['Associations'])) {
            trigger_error('no profile found. Ident: ' . $Ident . ', Value: ' . $Value);
            return null;
        }

        // 2. Debugging
        if ($this->debug) {
            call_user_func($this->Logger_Dbg, __FUNCTION__, 'Profile "' . $Ident . '" found: ' . json_encode($selectedProfile, JSON_THROW_ON_ERROR));
        }

        // 3. Wert im Profil suchen
        foreach ($selectedProfile['Associations'] as $item) {
            $foundSubCommand = $this->matchValueInAssociation($selectedProfile['Type'], $item, $Value);

            if ($foundSubCommand !== null) {
                return (string)$foundSubCommand;
            }
        }

        // Nichts gefunden
        trigger_error('no association found. Ident: ' . $Ident . ', Value: ' . $Value);
        return null;
    }

    /**
     * Hilfsmethode um die komplexe Switch-Logik und Magic-Numbers zu isolieren
     */
    private function matchValueInAssociation(int $type, array $item, $Value): ?string
    {
        switch ($type) {
            case DENONIPSVarType::vtBoolean:
                // Boolean: Vergleich Index 0, Return Index 1
                if ($item[0] === $Value) {
                    return $item[1];
                }
                break;

            case DENONIPSVarType::vtInteger:
                // Integer: Vergleich Index 0, Return Index 2
                if ($item[0] === $Value) {
                    return $item[2];
                }
                break;

            case DENONIPSVarType::vtFloat:
                // Float: Vergleich Index 1 (gerundet), Return Index 0
                // Achtung: Floats mit Nachkommastellen müssen zum Vergleich gerundet werden!
                if (round($item[1], 1) === round($Value, 1)) {
                    return $item[0];
                }
                break;

            default:
                // Optional: Fehler nur einmal loggen oder hier werfen,
                // aktuell wird er im Loop oben ignoriert, was okay ist.
                break;
        }
        return null;
    }

    public function GetSubCommandOfValueName(string $Ident, string $ValueName): ?string
    {
        $ret = null;
        foreach ($this->profiles as $profile) {
            if (($profile['Ident'] === $Ident) && isset($profile['Associations'])) {
                foreach ($profile['Associations'] as $item) {
                    if ($profile['Type'] === DENONIPSVarType::vtInteger) {
                        if (strtoupper($item[1]) === strtoupper($ValueName)) {
                            $ret = $item[2];
                        }
                    } else {
                        trigger_error(__FUNCTION__ . ': unknown type: ' . $profile['Type']);
                    }
                    if ($ret !== null) {
                        break;
                    }
                }
            }
            if ($ret !== null) {
                break;
            }
        }

        if ($ret === null) {
            trigger_error('no subcommand found. Ident: ' . $Ident . ', Value: ' . $ValueName);

            return null;
        }

        return (string) $ret;
    }

    private function getpos($profilename): false|int
    {
        $pos = array_search($profilename, static::$order, true);
        if ($pos === false) {
            trigger_error('unknown profile: ' . $profilename);

            return false;
        }

        return ($pos + 1) * 10; //starting with 10, step size 10
    }

    private function GetAssociationFromA1toG8(): array
    {
        $value_mapping = [];
        $index = 1;
        for ($i  = ord('A'); $i <= ord('G'); $i++){
            for ($j = 1; $j <= 8; $j++){
                $value_mapping[] = [$index, chr($i) . $j, chr($i) . $j];
                $index++;
            }
        }

        return $value_mapping;
    }

    private function GetAssociationFrom00to56(): array
    {
        $value_mapping = [];
        $index = 1;
        for ($i  = 1; $i <= 56; $i++){
            $value_mapping[] = [$index, sprintf('%02d', $i), sprintf('%02d',$i)];
            $index++;
        }

        return $value_mapping;
    }

    /**
     * Erzeugt eine Assoziationsliste für IP-Symcon Profil-Mappings zwischen ASCII-Protokollwerten und dB-Werten.
     *
     * @param string $asciiStart       Startwert des ASCII-Bereichs (z.B. '00')
     * @param string $asciiEnd         Endwert des ASCII-Bereichs (z.B. '98')
     * @param string $asciiReference   ASCII-Wert, der 0 dB entspricht (Referenzpunkt)
     * @param float  $dbStep           Schrittweite in dB (Standard: 1.0)
     * @param bool   $includeHalfSteps Ob zusätzlich 0.5er Zwischenschritte (z.B. '495' für 49.5) erzeugt werden sollen
     * @param bool   $useLeadingBlank  Ob ein führendes Leerzeichen im Label (für das Protokoll) nötig ist
     * @param bool   $invertDbValue    Ob der dB-Wert für die Anzeige invertiert werden soll (z. B. LFE-Level)
     * @param float  $scaleFactor      Skalierungsfaktor zur Umrechnung von ASCII-Differenz in dB
     *
     * @return array Generierte Liste von [Protokoll-String, dB-Float] Paaren
     * @throws \InvalidArgumentException
     * @throws \InvalidArgumentException
     */
    private function GetAssociationOfAsciiTodB(
        string $asciiStart,
        string $asciiEnd,
        string $asciiReference,
        float $dbStep = 1.0,
        bool $includeHalfSteps = false,
        bool $useLeadingBlank = true,
        bool $invertDbValue = false,
        float $scaleFactor = 1.0
    ): array {
        if ($dbStep <= 0 || $scaleFactor <= 0) {
            throw new InvalidArgumentException('StepSize and ScaleFactor must be greater than 0');
        }

        $startInt = (int)$asciiStart;
        $endInt   = (int)$asciiEnd;
        $refInt   = (int)$asciiReference;

        $dbRangeStart = ($startInt - $refInt) * $scaleFactor;
        $dbRangeEnd   = ($endInt - $refInt) * $scaleFactor;

        $sign = $invertDbValue ? -1 : 1;
        $prefix = $useLeadingBlank ? ' ' : '';
        $padLength = strlen($asciiEnd);

        $associations = [];
        $epsilon = 0.0001;

        for ($currentDb = $dbRangeStart; $currentDb <= $dbRangeEnd + $epsilon; $currentDb += $dbStep) {
            $currentAscii = $refInt + ($currentDb / $scaleFactor);

            $asciiFloor = floor($currentAscii + $epsilon);
            $isHalfStep = abs($currentAscii - $asciiFloor - 0.5) < $epsilon;

            if ($includeHalfSteps && $isHalfStep) {
                $baseVal = (int)$asciiFloor;
                $suffix = '5';
            } else {
                $baseVal = (int)round($currentAscii);
                $suffix = '';
            }

            $protocolString = $prefix . str_pad((string)$baseVal, $padLength, '0', STR_PAD_LEFT) . $suffix;
            $associations[] = [$protocolString, $currentDb * $sign];
        }

        return $associations;
    }

}
