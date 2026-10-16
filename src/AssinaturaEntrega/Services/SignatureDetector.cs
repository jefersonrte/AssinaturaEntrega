using AssinaturaEntrega.Models;
using OpenCvSharp;

namespace AssinaturaEntrega.Services;

public sealed class SignatureDetectionResult
{
    public bool Found { get; init; }
    /// <summary>Região detectada da assinatura no frame original.</summary>
    public Rect Region { get; init; }
    /// <summary>Recorte (clone) da assinatura. Cabe ao chamador dar Dispose.</summary>
    public Mat? Crop { get; init; }
}

/// <summary>
/// Detecta a região da assinatura em um frame: assume tinta escura sobre
/// papel/fundo mais claro. Usa threshold adaptativo + morfologia para unir
/// os traços em uma mancha e recorta a caixa que os envolve.
/// </summary>
public sealed class SignatureDetector
{
    private readonly SignatureDetectionSettings _settings;

    public SignatureDetector(SignatureDetectionSettings settings)
    {
        _settings = settings;
    }

    public SignatureDetectionResult Detect(Mat frameBgr)
    {
        if (frameBgr.Empty())
            return new SignatureDetectionResult { Found = false };

        int w = frameBgr.Width;
        int h = frameBgr.Height;
        double totalArea = (double)w * h;

        using var gray = new Mat();
        Cv2.CvtColor(frameBgr, gray, ColorConversionCodes.BGR2GRAY);

        using var blur = new Mat();
        Cv2.GaussianBlur(gray, blur, new Size(5, 5), 0);

        // Threshold adaptativo: tinta (escura) -> branco (255) sobre fundo preto.
        int block = _settings.ThresholdBlockSize;
        if (block % 2 == 0) block++;          // precisa ser ímpar
        if (block < 3) block = 3;

        using var bin = new Mat();
        Cv2.AdaptiveThreshold(
            blur, bin, 255,
            AdaptiveThresholdTypes.MeanC,
            ThresholdTypes.BinaryInv,
            block,
            _settings.ThresholdC);

        // Une os traços da assinatura em uma mancha contínua.
        using var kernel = Cv2.GetStructuringElement(MorphShapes.Rect, new Size(25, 15));
        using var closed = new Mat();
        Cv2.MorphologyEx(bin, closed, MorphTypes.Close, kernel, iterations: 2);

        Cv2.FindContours(
            closed,
            out Point[][] contours,
            out _,
            RetrievalModes.External,
            ContourApproximationModes.ApproxSimple);

        double minArea = totalArea * (_settings.MinAreaPercent / 100.0);

        int x0 = int.MaxValue, y0 = int.MaxValue, x1 = int.MinValue, y1 = int.MinValue;
        bool any = false;

        foreach (var c in contours)
        {
            var r = Cv2.BoundingRect(c);
            if ((double)r.Width * r.Height < minArea)
                continue;

            // Ignora manchas que ocupam quase o frame inteiro (provável borda/fundo).
            if ((double)r.Width * r.Height > totalArea * 0.95)
                continue;

            any = true;
            x0 = Math.Min(x0, r.X);
            y0 = Math.Min(y0, r.Y);
            x1 = Math.Max(x1, r.X + r.Width);
            y1 = Math.Max(y1, r.Y + r.Height);
        }

        if (!any)
            return new SignatureDetectionResult { Found = false };

        // Aplica margem.
        int padX = (int)(w * _settings.PaddingPercent / 100.0);
        int padY = (int)(h * _settings.PaddingPercent / 100.0);
        x0 = Math.Max(0, x0 - padX);
        y0 = Math.Max(0, y0 - padY);
        x1 = Math.Min(w, x1 + padX);
        y1 = Math.Min(h, y1 + padY);

        var region = new Rect(x0, y0, x1 - x0, y1 - y0);
        if (region.Width <= 0 || region.Height <= 0)
            return new SignatureDetectionResult { Found = false };

        var crop = new Mat(frameBgr, region).Clone();
        return new SignatureDetectionResult { Found = true, Region = region, Crop = crop };
    }
}
