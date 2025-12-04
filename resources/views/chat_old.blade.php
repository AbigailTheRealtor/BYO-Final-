<!DOCTYPE html>
<html>
<head>
  <!-- Include Bootstrap CSS -->
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
  <div class="container">
    <h1>Chat</h1>
    <form method="post" action="/chat">
        @csrf
      <div class="form-group">
        <label for="question">Question:</label>
        <input type="text" class="form-control" id="question" placeholder="Enter your question" name="question">
      </div>
      <input type="hidden" name="id"  value={{$id}}>
      <!-- Add more form fields here if needed -->
      <button type="submit" class="btn btn-primary">Submit</button>
    </form>
  </div>
  <div class="container">
    @if(isset($bestAnswer))
    <p>Answer: {{ $bestAnswer }}</p>
    @endif
  </div>

  <!-- Include Bootstrap JS -->
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
