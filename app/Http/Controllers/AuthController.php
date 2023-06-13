<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Validator;
use jcobhams\NewsApi\NewsApi;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct() {
        $this->middleware('auth:api', ['except' => ['login', 'register']]);
    }
    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request){
    	$validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        if (! $token = auth()->attempt($validator->validated())) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        return $this->createNewToken($token);
    }
    /**
     * Register a User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request) {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100|unique:users',
            'password' => 'required|string|confirmed|min:6',
        ]);
        if($validator->fails()){
            return response()->json($validator->errors()->toJson(), 400);
        }
        $user = User::create(array_merge(
                    $validator->validated(),
                    ['password' => bcrypt($request->password)]
                ));
        return response()->json([
            'message' => 'User successfully registered',
            'user' => $user
        ], 201);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout() {
        auth()->logout();
        return response()->json(['message' => 'User successfully signed out']);
    }
    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh() {
        return $this->createNewToken(auth()->refresh());
    }
    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function userProfile() {
        return response()->json(auth()->user());
    }

    public function updateCategory(Request $request) {
        $reqdata['category'] = $request->category;
        $update = User::where('id',$request->id)->update($reqdata);
        if ($update) {
            return response()->json([
                'message' => 'Category updated successfully',
                'status' => 200
            ]);
        }
    }

    public function newsFeed() {
        $category_array = json_decode(auth()->user()->category);
        $category = [];
        for ($i=0; $i < sizeof($category_array); $i++) { 
            if($category_array[$i]->active == true) {
                array_push($category, $category_array[$i]->name);
            }
        }
        return $this->getNewsFeed($category);
    }

    public function getNewsFeed($category) {
        $api_key = "e42d5ad7-bb8e-449e-956a-102642361e96";
        $httpClient = new \GuzzleHttp\Client();
        // $api_key = "ce84600046c64e09aff71f4eb4c2bbc9";
        // $newsapi = new NewsApi($api_key);
        // $finalData = [];
        // for ($i=0; $i < sizeof($category); $i++) { 
        //     $top_headlines = $newsapi->getTopHeadlines(null, null, null, "business");
        //     array_push($finalData, $top_headlines->sources);
        // }
        // return $finalData;
        
        $finalData = [];
        for ($i=0; $i < sizeof($category); $i++) { 
            $request = $httpClient->get("https://content.guardianapis.com/search?q=".$category[$i]."&api-key=e42d5ad7-bb8e-449e-956a-102642361e96");
            $response = json_decode($request->getBody()->getContents());
            array_push($finalData, $response->response->results);
        }
        return response()->json([
            'data' => $finalData[0],
            'status' => 200
        ]);
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function createNewToken($token){
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => auth()->user(),
            'message' => 'Login successfully',
            'status' => 200
        ]);
    }
}