param([string]$Root)
Add-Type -AssemblyName System.Drawing
$cs = @'
using System;
using System.Drawing;
using System.Drawing.Drawing2D;
using System.Drawing.Imaging;
using System.Runtime.InteropServices;
public static class LogoTool {
  // Soft key: pixels within `lo` of the background go fully transparent, pixels
  // `hi` or further away stay opaque, and the ramp between is un-blended so
  // edges keep their true colour rather than a cream fringe.
  public static Bitmap Key(Bitmap src, int bgR, int bgG, int bgB, double lo, double hi) {
    var dst = new Bitmap(src.Width, src.Height, PixelFormat.Format32bppArgb);
    var r = new Rectangle(0, 0, src.Width, src.Height);
    var sd = src.LockBits(r, ImageLockMode.ReadOnly, PixelFormat.Format32bppArgb);
    var dd = dst.LockBits(r, ImageLockMode.WriteOnly, PixelFormat.Format32bppArgb);
    int n = sd.Stride * src.Height;
    var s = new byte[n]; var d = new byte[n];
    Marshal.Copy(sd.Scan0, s, 0, n);
    for (int i = 0; i < n; i += 4) {
      int b = s[i], g = s[i+1], rr = s[i+2];
      int dist = Math.Max(Math.Max(Math.Abs(rr-bgR), Math.Abs(g-bgG)), Math.Abs(b-bgB));
      double a = (dist - lo) / (hi - lo);
      if (a < 0) a = 0; if (a > 1) a = 1;
      if (a == 0) { d[i]=d[i+1]=d[i+2]=0; d[i+3]=0; continue; }
      d[i]   = Clamp((b  - (1-a)*bgB) / a);
      d[i+1] = Clamp((g  - (1-a)*bgG) / a);
      d[i+2] = Clamp((rr - (1-a)*bgR) / a);
      d[i+3] = (byte)Math.Round(a * 255);
    }
    Marshal.Copy(d, 0, dd.Scan0, n);
    src.UnlockBits(sd); dst.UnlockBits(dd);
    return dst;
  }
  static byte Clamp(double v) { return (byte)(v < 0 ? 0 : v > 255 ? 255 : Math.Round(v)); }

  public static Bitmap Crop(Bitmap src, int x, int y, int w, int h) {
    return src.Clone(new Rectangle(x, y, w, h), PixelFormat.Format32bppArgb);
  }

  static Graphics HQ(Bitmap b) {
    var g = Graphics.FromImage(b);
    g.InterpolationMode = InterpolationMode.HighQualityBicubic;
    g.SmoothingMode = SmoothingMode.HighQuality;
    g.PixelOffsetMode = PixelOffsetMode.HighQuality;
    g.CompositingQuality = CompositingQuality.HighQuality;
    return g;
  }

  public static Bitmap Scale(Bitmap src, int w, int h) {
    var b = new Bitmap(w, h, PixelFormat.Format32bppArgb);
    using (var g = HQ(b)) g.DrawImage(src, 0, 0, w, h);
    return b;
  }

  // Roundel on the left, name block on the right, vertically centred.
  public static Bitmap Wordmark(Bitmap roundel, Bitmap name, int height, double nameFrac, double gapFrac) {
    int rw = (int)Math.Round(height * (double)roundel.Width / roundel.Height);
    int nh = (int)Math.Round(height * nameFrac);
    int nw = (int)Math.Round(nh * (double)name.Width / name.Height);
    int gap = (int)Math.Round(height * gapFrac);
    var b = new Bitmap(rw + gap + nw, height, PixelFormat.Format32bppArgb);
    using (var g = HQ(b)) {
      g.DrawImage(roundel, 0, 0, rw, height);
      g.DrawImage(name, rw + gap, (height - nh) / 2, nw, nh);
    }
    return b;
  }

  // Square canvas, optional solid fill, source scaled to `frac` of the side and centred.
  public static Bitmap Square(Bitmap src, int size, double frac, string fill) {
    var b = new Bitmap(size, size, PixelFormat.Format32bppArgb);
    using (var g = HQ(b)) {
      if (fill != null) g.Clear(ColorTranslator.FromHtml(fill));
      double s = Math.Min(size * frac / src.Width, size * frac / src.Height);
      int w = (int)Math.Round(src.Width * s), h = (int)Math.Round(src.Height * s);
      g.DrawImage(src, (size - w) / 2, (size - h) / 2, w, h);
    }
    return b;
  }
}
'@
Add-Type -TypeDefinition $cs -ReferencedAssemblies System.Drawing

$img = Join-Path $Root 'assets\images'
$brand = Join-Path $Root 'branding'
New-Item -ItemType Directory -Force $brand | Out-Null

$orig = New-Object System.Drawing.Bitmap (Join-Path $brand 'shezen_logo_original.png')
$keyed = [LogoTool]::Key($orig, 252, 242, 245, 10, 60)

# Full lockup: trimmed to content with a small margin, downscaled to 1000 wide.
$pad = 24
$full = [LogoTool]::Crop($keyed, 249-$pad, 142-$pad, (1091-249)+2*$pad, (1129-142)+2*$pad)
$fullS = [LogoTool]::Scale($full, 1000, [int][Math]::Round(1000 * $full.Height / $full.Width))
$fullS.Save((Join-Path $img 'shezen_logo.png'), [System.Drawing.Imaging.ImageFormat]::Png)

# Roundel alone (with its glow and sparkles), and the SheZen + HARMONY block.
# Bottom edge stays tight: the name band begins 5px below the roundel.
$roundel = [LogoTool]::Crop($keyed, 290-$pad, 142-$pad, (959-290)+2*$pad, (778-142)+$pad+2)
$name = [LogoTool]::Crop($keyed, 249, 783, 1091-249, 1062-783)

$wm = [LogoTool]::Wordmark($roundel, $name, 600, 0.5, 0.07)
$wmS = [LogoTool]::Scale($wm, [int][Math]::Round(320 * $wm.Width / $wm.Height), 320)
$wmS.Save((Join-Path $img 'shezen_logo_wordmark.png'), [System.Drawing.Imaging.ImageFormat]::Png)

# Launcher icon and splash mark: these sit on a solid cream matching the
# artwork, so cut them from the untouched original rather than the keyed copy.
$opaque = [LogoTool]::Crop($orig, 290-$pad, 142-$pad, (959-290)+2*$pad, (778-142)+$pad+2)
[LogoTool]::Square($opaque, 1024, 0.86, '#FCF2F5').Save((Join-Path $brand 'icon.png'), [System.Drawing.Imaging.ImageFormat]::Png)
[LogoTool]::Square($opaque, 1024, 0.62, '#FCF2F5').Save((Join-Path $brand 'icon_foreground.png'), [System.Drawing.Imaging.ImageFormat]::Png)
[LogoTool]::Square($opaque, 720, 1.0, '#FCF2F5').Save((Join-Path $brand 'splash_mark.png'), [System.Drawing.Imaging.ImageFormat]::Png)

"full   : $($fullS.Width)x$($fullS.Height)"
"wordmark: $($wmS.Width)x$($wmS.Height)"
