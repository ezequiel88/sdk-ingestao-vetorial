namespace IngestaoVetorial.SDK.Exceptions;

/// <summary>Raised when the API returns an HTTP 4xx or 5xx response.</summary>
public sealed class ApiException : Exception
{
    public int StatusCode { get; }
    public string ResponseBody { get; }

    public ApiException(int statusCode, string responseBody)
        : base($"API error {statusCode}: {responseBody}")
    {
        StatusCode = statusCode;
        ResponseBody = responseBody;
    }
}
