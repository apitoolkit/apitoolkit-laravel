<?php

    use Illuminate\Support\Facades\Route;
    use APIToolkit\SDKs\PHPSDK;
    use Illuminate\Support\Facades\Http;

    //Serve this on port 8000 - php artisan serve
    Route::get('/users/{user_id}/delete', function () {
        
    })->middleware(PHPSDK::class);

    //Serve this on port 8001 - php artisan serve --port 8001
    Route::get('/delete', function () {
        $data = Http::withBody(json_encode(["mode"=>"admin"]), 'application/json')
            ->get("http://127.0.0.1:8000/users/123456/delete")->json();
        echo json_encode($data);
    });

?>