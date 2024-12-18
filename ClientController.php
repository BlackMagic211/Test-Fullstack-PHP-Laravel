namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redis;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class ClientController extends Controller
{
    // Create - Menyimpan client ke database dan Redis
    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required|max:250',
            'slug' => 'required|max:100|unique:my_client,slug',
            'client_logo' => 'required|image|mimes:jpg,jpeg,png,gif|max:10240',
        ]);

        // Upload gambar ke S3
        $clientLogoUrl = $this->uploadToS3($request->file('client_logo'));

        $client = Client::create([
            'name' => $request->name,
            'slug' => $request->slug,
            'client_logo' => $clientLogoUrl,
            'address' => $request->address,
            'phone_number' => $request->phone_number,
            'city' => $request->city,
        ]);

        // Simpan data ke Redis
        Redis::set($client->slug, $client->toJson());

        return response()->json($client, 201);
    }

    // Read - Mendapatkan client berdasarkan slug dari Redis atau PostgreSQL
    public function show($slug)
    {
        $client = Redis::get($slug);

        if (!$client) {
            // Jika tidak ada di Redis, ambil dari PostgreSQL
            $client = Client::where('slug', $slug)->firstOrFail();
            Redis::set($slug, $client->toJson());
        }

        return response()->json(json_decode($client));
    }

    // Update - Update client dan cache Redis
    public function update(Request $request, $slug)
    {
        $request->validate([
            'name' => 'required|max:250',
            'client_logo' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:10240',
        ]);

        $client = Client::where('slug', $slug)->firstOrFail();

        // Update data client
        $client->update([
            'name' => $request->name,
            'address' => $request->address,
            'phone_number' => $request->phone_number,
            'city' => $request->city,
        ]);

        // Update client_logo jika ada file baru
        if ($request->hasFile('client_logo')) {
            $clientLogoUrl = $this->uploadToS3($request->file('client_logo'));
            $client->update(['client_logo' => $clientLogoUrl]);
        }

        // Hapus data Redis yang lama dan simpan yang baru
        Redis::del($slug);
        Redis::set($client->slug, $client->toJson());

        return response()->json($client);
    }

    // Delete - Soft delete client dan update Redis
    public function destroy($slug)
    {
        $client = Client::where('slug', $slug)->firstOrFail();

        // Soft delete dengan mengupdate deleted_at
        $client->update(['deleted_at' => now()]);

        // Hapus data di Redis
        Redis::del($slug);

        return response()->json(['message' => 'Client soft deleted']);
    }

    // Fungsi untuk mengunggah gambar ke S3
    private function uploadToS3($file)
    {
        $s3Client = new S3Client([
            'version' => 'latest',
            'region'  => 'us-east-1',
            'credentials' => [
                'key'    => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ]
        ]);

        $filePath = $file->store('client_logos', 's3');
        return $s3Client->getObjectUrl(env('AWS_BUCKET'), $filePath);
    }
}
