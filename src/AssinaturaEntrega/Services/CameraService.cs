using System.Windows.Media.Imaging;
using AssinaturaEntrega.Models;
using OpenCvSharp;
using OpenCvSharp.WpfExtensions;

namespace AssinaturaEntrega.Services;

/// <summary>
/// Captura frames da webcam com OpenCV e entrega como BitmapSource (WPF).
/// A leitura roda em uma Task própria; cada frame novo dispara FrameReady.
/// </summary>
public sealed class CameraService : IDisposable
{
    private readonly CameraSettings _settings;
    private VideoCapture? _capture;
    private CancellationTokenSource? _cts;
    private Task? _loop;
    private readonly object _frameLock = new();
    private Mat? _lastFrame;

    public event Action<BitmapSource>? FrameReady;
    public event Action<string>? Error;

    public bool IsRunning { get; private set; }

    public CameraService(CameraSettings settings)
    {
        _settings = settings;
    }

    public void Start()
    {
        if (IsRunning) return;

        _capture = new VideoCapture(_settings.DeviceIndex);
        if (!_capture.IsOpened())
        {
            _capture.Dispose();
            _capture = null;
            Error?.Invoke($"Não foi possível abrir a câmera (índice {_settings.DeviceIndex}). " +
                          "Verifique se a webcam está conectada e não está em uso por outro programa.");
            return;
        }

        _capture.Set(VideoCaptureProperties.FrameWidth, _settings.Width);
        _capture.Set(VideoCaptureProperties.FrameHeight, _settings.Height);

        _cts = new CancellationTokenSource();
        var token = _cts.Token;
        IsRunning = true;

        _loop = Task.Run(() => CaptureLoop(token), token);
    }

    private void CaptureLoop(CancellationToken token)
    {
        using var frame = new Mat();
        while (!token.IsCancellationRequested && _capture != null)
        {
            try
            {
                if (!_capture.Read(frame) || frame.Empty())
                {
                    Thread.Sleep(30);
                    continue;
                }

                lock (_frameLock)
                {
                    _lastFrame?.Dispose();
                    _lastFrame = frame.Clone();
                }

                // Converte para BitmapSource (precisa ser congelado para cruzar threads).
                BitmapSource src = frame.ToBitmapSource();
                src.Freeze();
                FrameReady?.Invoke(src);
            }
            catch (Exception ex)
            {
                Error?.Invoke("Erro ao ler frame da câmera: " + ex.Message);
                break;
            }

            // ~30 fps
            Thread.Sleep(33);
        }
    }

    /// <summary>Retorna uma cópia do último frame capturado (BGR), ou null.</summary>
    public Mat? GrabFrame()
    {
        lock (_frameLock)
        {
            return _lastFrame?.Clone();
        }
    }

    public void Stop()
    {
        IsRunning = false;
        try { _cts?.Cancel(); } catch { }
        try { _loop?.Wait(1000); } catch { }

        _capture?.Release();
        _capture?.Dispose();
        _capture = null;

        lock (_frameLock)
        {
            _lastFrame?.Dispose();
            _lastFrame = null;
        }

        _cts?.Dispose();
        _cts = null;
    }

    public void Dispose() => Stop();
}
