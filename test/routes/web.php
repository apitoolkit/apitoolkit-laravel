<?php

    use Illuminate\Support\Facades\Route;
    use APIToolkit\SDKs\PHPSDK;
    use Illuminate\Support\Facades\Http;

    //Simple tests for GET and POST

    //Serve this on port 8000 - php artisan serve (Delete user)
    Route::post('/users/{user_id}/delete', function () {
        return Response::json([
            "status"=>"success",
            "msg"=>"User Deleted"
        ], 200);
    })->middleware(PHPSDK::class);

    //Serve this on port 8000 - php artisan serve (Get user information)
    Route::get('/profiles/{user_id}/information', function () {
        return Response::json([
            "status"=>"success",
            "data"=>[
                "first_name"=>"John",
                "last_name"=>"Doe",
                "email"=>"someone@example.com",
                "phone"=>"+23480123456789",
                "nick"=>"Jay_Doe"
            ]
        ], 200);
    })->middleware(PHPSDK::class);

    //Serve this on port 8001 - php artisan serve --port 8001
    //Used to delete users from /users/{user_id}/delete
    Route::get('/delete', function () {
        /*
        $data = Http::withBody(json_encode(["mode"=>"admin"]), 'application/json')
            ->post("http://127.0.0.1:8000/users/123456/delete")->json();

        return Response::json($data, 200);
        */
    });

    //Serve this on port 8001 - php artisan serve --port 8001
    //Used to fetch profile information from /profiles/{user_id}/information
    
    Route::get('/info', function () {
        $data = Http::withBody(json_encode(["as"=>"user"]), 'application/json')
            ->post("http://127.0.0.1:8000/profiles/123456/information")->json();

        return Response::json($data, 200);
    });

?>