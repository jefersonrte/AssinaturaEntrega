namespace AssinaturaEntrega.Models;

public class AppSettings
{
    public CameraSettings Camera { get; set; } = new();
    public SignatureDetectionSettings SignatureDetection { get; set; } = new();
    public ArchiveSettings Archive { get; set; } = new();
    public ApiSettings Api { get; set; } = new();
    public DeviceSettings Device { get; set; } = new();
}

public class CameraSettings
{
    public int DeviceIndex { get; set; } = 0;
    public int Width { get; set; } = 1280;
    public int Height { get; set; } = 720;
}

public class SignatureDetectionSettings
{
    public bool Enabled { get; set; } = true;
    /// <summary>Margem extra (% da dimensão) ao redor da região detectada.</summary>
    public double PaddingPercent { get; set; } = 6;
    /// <summary>Área mínima (% da imagem) para um bloco de tinta ser considerado.</summary>
    public double MinAreaPercent { get; set; } = 0.5;
    /// <summary>Tamanho do bloco do threshold adaptativo (ímpar).</summary>
    public int ThresholdBlockSize { get; set; } = 35;
    /// <summary>Constante C subtraída na média do threshold adaptativo.</summary>
    public int ThresholdC { get; set; } = 15;
}

public class ArchiveSettings
{
    public string RootPath { get; set; } = "%USERPROFILE%\\Documents\\AssinaturasEntrega";
    public bool SaveOriginal { get; set; } = true;
    public bool SaveSignatureCrop { get; set; } = true;
}

public class ApiSettings
{
    public bool Enabled { get; set; } = true;
    public string BaseUrl { get; set; } = "";
    public string UploadPath { get; set; } = "/api/assinaturas";
    public string PingPath { get; set; } = "/api/ping";
    public string AuthToken { get; set; } = "";
    public int TimeoutSeconds { get; set; } = 30;
    public bool SendOriginal { get; set; } = false;
}

public class DeviceSettings
{
    public string DeviceId { get; set; } = "ENTREGA-01";
}
